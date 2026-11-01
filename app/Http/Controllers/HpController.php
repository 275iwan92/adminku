<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Item;
use App\Models\StockDocument;
use App\Enums\StockDocumentType;
use App\Services\CountSchedule;
use App\Support\HpShell;
use App\Support\Modules;
use App\Support\Search;
use Devlib\Device;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\Rule;

/* REMARK
 * Tujuan       : Tampilan HP (smartphone potret) selain Hitung Opname: Beranda ringkas, daftar modul satu kelompok, modul yang belum punya tampilan HP dibuka (halaman desktop) di dalam kerangka HP, dan penyimpan pilihan tampilan.
 *                Logika dan hak akses tidak berubah: layar desktop tetap jalan; ini hanya tampilan lain untuk perangkat yang berbeda.
 * Sumber hulu  : HpShell (tab / modul), Modules (kode modul, hak), Device (cookie ui).
 * Output       : hp.home (dipanggil HomeController bila perangkat = hp), hp.group, hp.frame, hp.find / hp.find.show (Cari Barang, modul ITM); cookie ui.
 * Dipakai oleh : routes/web.php (hp.group, hp.open, hp.find, ui.set), HomeController.
 * Status       : Aktif.
 */
class HpController extends Controller
{
    // Layar super admin yang dibuka lewat hp.open: kode => [judul, nama rute halaman desktopnya].
    private const SUPER = ['LOG' => ['Log Aktivitas', 'super.log'], 'UAT' => ['Akun UAT', 'super.accounts']];

    private const FIND_LIMIT = 30;

    // Beranda HP: ringkasan sebagai kartu yang membuka modulnya. Angka sama dengan Beranda desktop (kecuali nilai stok & menunggu persetujuan hanya Supervisor).
    public function home(Request $request)
    {
        $user = $request->user();
        $below = Item::query()->belowReorderPoint()->withAvailable()->count();
        $expiring = Batch::query()->nearExpiry()->get();
        $docs = fn (StockDocumentType $t) => StockDocument::query()->where('type', $t)->whereIn('status', ['confirmed', 'cancel_pending'])->whereDate('doc_at', today())->count();
        $cards = array_values(array_filter([
            ['label' => 'Barang aktif', 'value' => Item::query()->where('is_active', true)->count(), 'icon' => 'fa-solid fa-boxes-stacked', 'code' => 'ITM'],
            ['label' => 'Di / bawah titik pesan ulang', 'value' => $below, 'icon' => 'fa-solid fa-triangle-exclamation', 'code' => 'ITM', 'tone' => $below ? 'danger' : ''],
            ['label' => 'Hampir / sudah kedaluwarsa', 'value' => $expiring->count(), 'icon' => 'fa-solid fa-hourglass-end', 'code' => 'R-EXP', 'tone' => $expiring->isNotEmpty() ? 'warn' : ''],
            ['label' => 'Penerimaan hari ini', 'value' => $docs(StockDocumentType::Receiving), 'icon' => 'fa-solid fa-truck-ramp-box', 'code' => 'RCV'],
            ['label' => 'Pengeluaran hari ini', 'value' => $docs(StockDocumentType::Issuance), 'icon' => 'fa-solid fa-dolly', 'code' => 'ISS'],
            $user->isSupervisor() ? ['label' => 'Menunggu persetujuan', 'value' => StockDocument::query()->whereIn('status', ['approval', 'cancel_pending'])->count(), 'icon' => 'fa-solid fa-scale-balanced', 'code' => 'ADJ'] : null,
        ]));

        return view('hp.home', [
            'title' => 'Beranda', 'active' => 'home',
            'cards' => array_values(array_filter($cards, fn ($c) => $user->canAccess($c['code']))),
            'countReminder' => $user->canAccess('SO') && CountSchedule::available() ? CountSchedule::state() : null,
        ]);
    }

    // Tab Transaksi (dan kelompok lain): daftar modul yang boleh dibuka.
    public function group(Request $request, string $parent)
    {
        abort_unless(isset(Modules::PARENTS[$parent]), 404);
        $items = collect(HpShell::modules($request->user()))->firstWhere('code', $parent)['children'] ?? [];

        return view('hp.group', ['title' => Modules::PARENTS[$parent][0], 'active' => ['TRX' => 'trx', 'RPT' => 'report'][$parent] ?? '', 'items' => $items]);
    }

    // Modul tanpa tampilan HP: halaman desktop modul itu di dalam kerangka HP. Hak akses dijaga dua kali: di sini (modul boleh dibuka) dan oleh rute halaman desktop itu sendiri.
    public function open(Request $request, string $code)
    {
        // Layar super admin (tidak ada di Modules): juga dibuka di dalam kerangka HP supaya tombol menu tetap ada; selain super admin = 404.
        if (isset(self::SUPER[$code])) {
            abort_unless($request->user()->isSuperAdmin(), 404);

            return view('hp.frame', ['title' => self::SUPER[$code][0], 'active' => '', 'frame' => route(self::SUPER[$code][1])]);
        }

        $module = Modules::all()[$code] ?? null;
        abort_if($module === null, 404);
        abort_unless($request->user()->canAccess($code), 403, 'Anda tidak punya akses ke modul ini.');

        return view('hp.frame', [
            'title' => $module[1], 'active' => HpShell::tabFor($code),
            'frame' => is_array($module[4]) ? route($module[4][0], $module[4][1]) : route($module[4]),
        ]);
    }

    // Cari Barang: kode produk / kode batch / nama / kode lokasi. Kode lengkap (produk atau batch) langsung membuka barangnya, selain itu kartu hasil. Pemindai barcode = keyboard + Enter.
    public function find(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $products = null;
        if ($q !== '') {
            $exact = Item::query()->where('is_active', true)->whereRaw('lower(code) = ?', [mb_strtolower($q)])->first();
            $batch = $exact ? null : Batch::query()->whereHas('item', fn ($w) => $w->where('is_active', true))->whereRaw('lower(code) = ?', [mb_strtolower($q)])->first();
            if ($exact || $batch) {
                return redirect()->route('hp.find.show', ['product' => $exact?->id ?? $batch->item_id, 'b' => $batch?->code]);
            }
            $products = Search::apply(Item::query()->withAvailable()->where('is_active', true), $q, ['name', 'location'], ['code'], fn ($w, $like) => $w->orWhereHas('batches', fn ($i) => $i->whereRaw("code like ? escape '!'", [$like])))
                ->orderBy('name')->limit(self::FIND_LIMIT)->get();
        }

        return view('hp.find', ['title' => 'Cari Barang', 'active' => 'stock', 'back' => route('home'), 'q' => $q, 'products' => $products, 'limit' => self::FIND_LIMIT]);
    }

    // Stok satu barang per batch, urutan FEFO (kedaluwarsa paling awal dulu, tanpa tanggal terakhir). Nilai uang hanya untuk Supervisor.
    public function product(Request $request, Item $product)
    {
        abort_unless($product->is_active, 404);
        $product = Item::query()->withAvailable()->findOrFail($product->id);
        $batches = $product->batches()->where('is_active', true)->where('stock_qty', '>', 0)->orderByRaw('expiry_date is null')->orderBy('expiry_date')->orderBy('id')->get();

        return view('hp.find-show', [
            'title' => $product->name, 'active' => 'stock', 'back' => route('hp.find'),
            'product' => $product, 'batches' => $batches, 'scanned' => $request->query('b'),
            'value' => $request->user()->isSupervisor() ? $batches->sum(fn (Batch $i) => $i->stockValue()) : null,
        ]);
    }

    // Simpan pilihan tampilan (phone | desktop; nilai lama hp = phone) di cookie `ui`; pilihan pengguna selalu menang atas tebakan perangkat.
    // auto=1 (x-dl::phone-offer auto / x-dl::phone-shell autoReturn) = peralihan otomatis karena lebar layar: cookie ui_auto menandainya sebagai BUKAN pilihan pengguna; tanpa auto, penanda dibuang.
    public function setDevice(Request $request): RedirectResponse
    {
        $data = $request->validate(['ui' => ['required', Rule::in([...Device::CHOICES, ...array_keys(Device::ALIASES)])], 'auto' => ['sometimes', 'boolean']]);

        return redirect()->route('home')
            ->withCookie(cookie(Device::COOKIE, Device::normalize($data['ui']), 60 * 24 * 365))
            ->withCookie($request->boolean('auto') ? cookie(Device::AUTO_COOKIE, '1', 60 * 24 * 365) : Cookie::forget(Device::AUTO_COOKIE));
    }
}
