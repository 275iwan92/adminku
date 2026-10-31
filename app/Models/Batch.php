<?php

namespace App\Models;

use App\Support\Decimal;
use Devlib\Locale;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['product_id', 'code', 'name', 'category_id', 'unit', 'location', 'expiry_date', 'unit_price', 'reorder_point', 'reorder_qty', 'lead_time_days', 'is_active'])]
class Batch extends Model
{
    protected $table = 'i_batch';

    // Peringatan "hampir kedaluwarsa" muncul bila tinggal sekian hari.
    public const EXPIRY_WARN_DAYS = 30;

    protected function casts(): array
    {
        return [
            'unit_price' => 'float',
            'reorder_point' => 'float',
            'reorder_qty' => 'float',
            'stock_qty' => 'float',
            'avg_cost' => 'float',
            'lead_time_days' => 'integer',
            'expiry_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StockDocumentLine::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    // Batch yang masih bisa dipakai: tanpa tanggal kedaluwarsa, atau belum kedaluwarsa (config sstock.reorder_exclude_days menambah jendela hampir-kedaluwarsa).
    public function scopeUsable(Builder $query): Builder
    {
        return $query->where(fn ($w) => $w->whereNull('expiry_date')->orWhere('expiry_date', '>=', self::usableFrom()));
    }

    public static function usableFrom(): string
    {
        return today()->addDays((int) config('sstock.reorder_exclude_days', 0))->toDateString();
    }

    // Teks pilihan di dokumen: kode batch, nama, dan tanggal kedaluwarsanya.
    public function label(): string
    {
        return "{$this->code} · {$this->name}".($this->expiry_date ? ' · exp '.Locale::d($this->expiry_date) : '');
    }

    // Barang aktif yang masih ada stoknya dan sudah kedaluwarsa atau akan kedaluwarsa dalam $days hari (stok 0 tidak perlu dibuang, jadi tidak diperingatkan).
    public function scopeNearExpiry(Builder $query, int $days = self::EXPIRY_WARN_DAYS): Builder
    {
        return $query->where('is_active', true)->where('stock_qty', '>', 0)->whereNotNull('expiry_date')->whereDate('expiry_date', '<=', today()->addDays($days));
    }

    // KEDALUWARSA = tanggalnya sudah lewat, HAMPIR = tinggal $days hari atau kurang, null = aman / tanpa kedaluwarsa.
    public function expiryState(int $days = self::EXPIRY_WARN_DAYS): ?string
    {
        return match (true) {
            ! $this->expiry_date => null,
            $this->expiry_date->lt(today()) => 'KEDALUWARSA',
            $this->expiry_date->lte(today()->addDays($days)) => 'HAMPIR',
            default => null,
        };
    }

    public function stockValue(): float
    {
        return Decimal::amount($this->stock_qty, $this->avg_cost);
    }
}
