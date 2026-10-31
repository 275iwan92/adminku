<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

/* REMARK
 * Tujuan       : Barang (produk / group): data induk dan parameter pesan ulang. Stok ada di batch (items): satu batch untuk barang biasa, satu batch per tanggal untuk barang berkedaluwarsa.
 *                Kode produk {AWALAN KATEGORI}-{URUT 4 DIGIT} tidak memuat tanggal; kode batch = kode produk, atau {kode produk}-{YYMMDD kedaluwarsa}.
 *                Titik pesan ulang dihitung dari stok TERSEDIA produk = jumlah stok batch yang belum kedaluwarsa (Item::usable).
 * Dipakai oleh : ProductController, StockDocuments, StockLedger, HomeController, ReportController.
 * Status       : Aktif.
 */
#[Fillable(['code', 'name', 'category_id', 'unit', 'location', 'unit_price', 'reorder_point', 'reorder_qty', 'lead_time_days', 'tracks_expiry', 'is_active'])]
class Product extends Model
{
    protected $table = 'm_item';

    // Kolom yang disalin ke setiap batch supaya dokumen dan laporan tetap membaca dari items.
    public const BATCH_COPY = ['name', 'category_id', 'unit', 'location', 'unit_price', 'reorder_point', 'reorder_qty', 'lead_time_days', 'is_active'];

    protected function casts(): array
    {
        return [
            'unit_price' => 'float', 'reorder_point' => 'float', 'reorder_qty' => 'float', 'lead_time_days' => 'integer',
            'tracks_expiry' => 'boolean', 'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    // Satuan masuk (kemasan saat diterima) beserta faktornya ke satuan stok.
    public function packUnits(): HasMany
    {
        return $this->hasMany(ProductUnit::class)->orderBy('unit');
    }

    // Pemesanan ulang yang masih menunggu barang datang (ditutup saat Receiving).
    public function openOrder(): HasOne
    {
        return $this->hasOne(ReorderOrder::class)->whereNull('closed_at')->latestOfMany();
    }

    // {AWALAN}-{URUT 4 DIGIT}; urutan berjalan per kategori. Pemanggil mengunci baris kategori agar dua penyimpan tidak mendapat nomor sama.
    public static function nextCode(Category $category): string
    {
        $last = static::query()->where('code', 'like', $category->code.'-%')->pluck('code')
            ->map(fn ($c) => (int) (explode('-', $c)[1] ?? 0))->max() ?? 0;

        return sprintf('%s-%04d', $category->code, $last + 1);
    }

    /** Produk baru (+ batch tunggal bila tidak berkedaluwarsa). $code diisi hanya oleh seeder / tes yang butuh kode tetap. */
    public static function register(array $data, ?string $code = null): static
    {
        return DB::transaction(function () use ($data, $code) {
            $category = Category::lockForUpdate()->findOrFail($data['category_id']);
            $product = static::create($data + ['code' => $code ?? static::nextCode($category)])->refresh();  // refresh: nilai bawaan database (aktif, 0) ikut terbaca untuk disalin ke batch
            if (! $product->tracks_expiry) {
                $product->batchFor(null);
            }

            return $product;
        });
    }

    // Batch untuk tanggal kedaluwarsa itu (dibuat bila belum ada). Barang biasa selalu satu batch tanpa tanggal.
    public function batchFor(Carbon|string|null $expiry): Item
    {
        $expiry = $this->tracks_expiry && $expiry ? Carbon::parse($expiry)->toDateString() : null;
        $code = $expiry ? $this->code.'-'.Carbon::parse($expiry)->format('ymd') : $this->code;

        // whereDate: tanggal tersimpan bisa berbentuk 'Y-m-d' atau 'Y-m-d 00:00:00' menurut basis data.
        $batch = $this->items()->when($expiry, fn ($q) => $q->whereDate('expiry_date', $expiry), fn ($q) => $q->whereNull('expiry_date'))->first();

        // createOrFirst: dua penyimpan bersamaan untuk batch baru yang sama -> yang kalah di kolom unik code mengambil batch yang sudah dibuat, bukan galat 500.
        return $batch ?? $this->items()->createOrFirst(['code' => $code], $this->only(self::BATCH_COPY) + ['expiry_date' => $expiry]);
    }

    // Parameter produk diteruskan ke semua batch-nya.
    public function propagate(): void
    {
        $this->items()->update($this->only(self::BATCH_COPY));
    }

    // Stok tersedia (batch yang belum kedaluwarsa) sebagai potongan SQL; dipakai scope dan urutan.
    public static function availableSql(): string
    {
        return '(select coalesce(sum(i.stock_qty), 0) from items i where i.product_id = m_item.id and (i.expiry_date is null or i.expiry_date >= ?))';
    }

    public function scopeWithAvailable(Builder $query): Builder
    {
        return $query->select('m_item.*')->selectRaw(self::availableSql().' as available_qty', [Item::usableFrom()]);
    }

    // Produk aktif yang stok tersedianya menyentuh atau di bawah titik pesan ulang (titik 0 = belum diisi, tidak dihitung).
    public function scopeBelowReorderPoint(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('reorder_point', '>', 0)->whereRaw(self::availableSql().' <= m_item.reorder_point', [Item::usableFrom()]);
    }

    public function available(): float
    {
        return (float) $this->items()->usable()->sum('stock_qty');
    }

    public function isBelowReorderPoint(): bool
    {
        return $this->is_active && $this->reorder_point > 0 && $this->available() <= $this->reorder_point;
    }
}
