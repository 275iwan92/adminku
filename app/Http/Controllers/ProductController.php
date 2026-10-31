<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Models\Category;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Unit;
use App\Models\User;
use App\Services\LeadTimes;
use App\Services\ProductUnits;
use App\Support\Palette;
use Devlib\Locale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/* REMARK
 * Tujuan       : Master Barang (produk / group). Semua pengguna boleh melihat dan menambah barang baru; hanya Supervisor mengubah parameter (harga, titik pesan ulang, lead time) dan mengaktifkan / menonaktifkan.
 *                Kode otomatis {AWALAN KATEGORI}-{URUT}. Stok ada di batch (items): barang biasa satu batch, barang berkedaluwarsa satu batch per tanggal (dibuat otomatis oleh Penerimaan / Saldo Awal).
 *                Parameter yang diubah diteruskan ke semua batch (Product::propagate).
 * Sumber hulu  : m_item, items (stok, biaya rata-rata, kedaluwarsa), m_category. Harga & nilai stok disembunyikan dari Data Entry.
 * Output       : grid Barang, form Barang (x-dl::form panel) dengan daftar batch.
 * Dipakai oleh : routes/web.php (products.*).
 * Status       : Aktif.
 */
class ProductController extends Controller
{
    public function index(Request $request)
    {
        $sup = $request->user()->isSupervisor();
        $rows = Product::query()->with('category')->withAvailable()
            ->selectRaw('(select coalesce(sum(stock_qty), 0) from i_batch where i_batch.product_id = m_item.id) as stock_total')
            ->selectRaw('(select coalesce(sum(stock_qty * avg_cost), 0) from i_batch where i_batch.product_id = m_item.id) as stock_value')
            ->selectRaw('(select min(expiry_date) from i_batch where i_batch.product_id = m_item.id and stock_qty > 0) as next_expiry')
            ->selectRaw('(select count(*) from i_batch where i_batch.product_id = m_item.id) as batch_count')
            ->orderBy('code')->get()->map(function (Product $p) use ($sup) {
                $below = $p->is_active && $p->reorder_point > 0 && (float) $p->available_qty <= $p->reorder_point;

                return [
                    'id' => $p->id, 'code' => $p->code, 'name' => $p->name, 'category' => $p->category->name, 'unit' => $p->unit, 'location' => $p->location,
                    'batches' => $p->tracks_expiry ? (int) $p->batch_count : null, 'expiry' => $p->next_expiry,
                    'stock' => (float) $p->stock_total, 'available' => (float) $p->available_qty, 'rop' => $p->reorder_point, 'roq' => $p->reorder_qty, 'lead' => $p->lead_time_days,
                    'price' => $sup ? $p->unit_price : null, 'value' => $sup ? round((float) $p->stock_value, 2) : null,
                    'hue' => Palette::item($p->category->code, $p->code), 'level' => $below ? 'PESAN ULANG' : 'AMAN', 'tone' => $below ? 'danger' : null, 'active' => $p->is_active,
                ];
            })->all();

        return view('products.index', ['rows' => $rows, 'sup' => $sup]);
    }

    public function create()
    {
        return view('products.form', ['product' => new Product(['unit' => 'pcs']), 'batches' => collect(), 'categories' => $this->categories(), 'units' => $this->units(), 'locations' => $this->locations(), 'nextCodes' => $this->nextCodes()]);
    }

    public function show(Request $request, Product $product)
    {
        return view('products.form', [
            'product' => $product, 'batches' => $product->batches()->orderByRaw('expiry_date is null')->orderBy('expiry_date')->get(), 'categories' => $this->categories(false),
            'units' => $this->units($product->unit), 'locations' => $this->locations($product->location), 'nextCodes' => [],
            'leadTime' => LeadTimes::for($product), 'dailyUsage' => LeadTimes::dailyUsage($product),
        ] + $this->packUnitData($product, $request->user()));
    }

    // Tambah / perbaiki satuan masuk barang (JSON dari dialog di form barang). Aturan siapa boleh mengubah faktor yang sudah dipakai: ProductUnits::save.
    public function saveUnit(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'unit' => ['required', 'exists:m_unit,code'],
            'factor' => ['required', 'numeric', 'gt:0', 'max:999999999'],
        ], ['factor.gt' => 'Faktor harus lebih dari 0.', 'factor.required' => 'Isi faktor satuan.']);
        ProductUnits::save($product, $data['unit'], $data['factor'], $request->user());
        session()->flash('status', 'Satuan masuk disimpan.');

        return response()->json(['ok' => true]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $product = Product::register($request->validated());

        return redirect()->route('products.show', $product)->with('status', 'Barang disimpan.');
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $action = $request->input('_action');
        if (in_array($action, ['activate', 'deactivate'], true)) {
            $this->setActive($product, $action === 'activate');

            return redirect()->route('products.show', $product)->with('status', $action === 'activate' ? 'Barang diaktifkan.' : 'Barang dinonaktifkan.');
        }

        // Faktor satuan masuk dihitung terhadap satuan stok; mengganti satuan stok membuatnya salah, jadi ditolak selama barang masih punya satuan masuk.
        if ($request->validated('unit') !== $product->unit && $product->packUnits()->exists()) {
            throw ValidationException::withMessages(['unit' => 'Satuan stok tidak bisa diganti selama barang ini punya Satuan masuk (faktornya mengacu ke satuan stok sekarang).']);
        }

        DB::transaction(function () use ($request, $product) {
            $product->update($request->validated());  // kode, kategori, dan tanda kedaluwarsa tidak berubah setelah disimpan
            $product->propagate();
        });

        return redirect()->route('products.show', $product)->with('status', 'Barang disimpan.');
    }

    // Dipanggil saklar Aktif/Nonaktif di grid (POST, balasan JSON {active}).
    public function toggle(Product $product): JsonResponse
    {
        $this->setActive($product, ! $product->is_active);

        return response()->json(['active' => $product->is_active]);
    }

    private function setActive(Product $product, bool $active): void
    {
        DB::transaction(function () use ($product, $active) {
            $product->update(['is_active' => $active]);
            $product->propagate();
        });
    }

    // Bagian "Satuan masuk" di form barang: baris tabel + pilihan satuan untuk dialog. Faktor yang sudah dipakai di dokumen hanya bisa diubah pemegang hak UOM, jadi pengguna lain tidak diberi tautan Ubah.
    private function packUnitData(Product $product, User $user): array
    {
        $used = ProductUnits::usage($product);
        $canUom = $user->canWrite('UOM');
        $packs = $product->packUnits()->with(['unitMaster', 'updater'])->get();

        return [
            'packRows' => $packs->map(fn (ProductUnit $u) => [
                'unit' => "{$u->unit} · {$u->unitMaster->name}", 'factor' => Locale::decimal($u->factor, 6), 'used' => ($used[$u->unit] ?? 0) ? ($used[$u->unit].' dokumen') : 'Belum dipakai',
                'by' => ($u->updater?->name ?? '—').' · '.Locale::stamp($u->updated_at),
                'act' => ! ($used[$u->unit] ?? 0) || $canUom ? ['href' => '#ubah-'.$u->unit, 'text' => 'Ubah'] : 'Hubungi Supervisor',
            ])->all(),
            'packJs' => $packs->mapWithKeys(fn (ProductUnit $u) => [$u->unit => ['factor' => rtrim(rtrim((string) $u->factor, '0'), '.'), 'used' => $used[$u->unit] ?? 0]])->all(),
            'packChoices' => Unit::query()->where('is_active', true)->where('code', '!=', $product->unit)->orderBy('code')->get()->mapWithKeys(fn (Unit $u) => [$u->code => "{$u->code} · {$u->name}"])->all(),
        ];
    }

    // Kategori aktif untuk barang baru; barang yang sudah ada menampilkan kategorinya sendiri (terkunci, kode memakai awalannya).
    private function categories(bool $activeOnly = true): array
    {
        return Category::query()->when($activeOnly, fn ($q) => $q->where('is_active', true))->orderBy('name')->pluck('name', 'id')->all();
    }

    // Kode berikutnya per kategori, untuk pratinjau di form: [category_id => 'ATK-0008'].
    private function nextCodes(): array
    {
        return Category::where('is_active', true)->get()->mapWithKeys(fn (Category $c) => [$c->id => Product::nextCode($c)])->all();
    }

    // Pilihan satuan = master Units aktif (+ satuan barang ini sendiri bila sudah dinonaktifkan).
    private function units(?string $keep = null): array
    {
        return Unit::query()->where('is_active', true)->orWhere('code', $keep)->orderBy('code')->get()->mapWithKeys(fn (Unit $u) => [$u->code => "{$u->code} · {$u->name}"])->all();
    }

    // Pilihan lokasi = master Lokasi aktif (+ lokasi barang ini sendiri bila sudah dinonaktifkan).
    private function locations(?string $keep = null): array
    {
        return Location::query()->where('is_active', true)->orWhere('code', $keep)->orderBy('code')->get()->mapWithKeys(fn (Location $l) => [$l->code => $l->label()])->all();
    }
}
