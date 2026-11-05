<?php

namespace App\Support;

use App\Enums\StockDocumentType;
use App\Models\AcOrder;
use App\Models\Batch;
use App\Models\Candidate;
use App\Models\CashAccount;
use App\Models\CashIn;
use App\Models\CashOut;
use App\Models\Discipline;
use App\Models\Employee;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestLine;
use App\Models\ReorderOrder;
use App\Models\StockDocument;
use App\Models\User;
use App\Services\CountSchedule;
use Devlib\Locale;
use Illuminate\Support\Facades\DB;

/* REMARK
 * Tujuan       : Layar pembuka kelompok menu / modul (dibuka dengan mengklik induk di panel kiri): Data Master, Transaksi, Laporan, GA, HR, Purchase. Satu pola: header, angka utama, perlu tindakan, pintasan, dan ubin layar anak menurut hak pengguna (lencana Tulis / Baca, jumlah data).
 *                Ubin = butir menu yang memang boleh dilihat pengguna (Menu::modules / Menu::layers), jadi tidak ada layar di luar hak. Stok membuka Beranda Stok (StockHomeController); HR dan Purchase belum punya layanan, jadi hanya rencana layar "Segera hadir", tanpa angka.
 * Sumber hulu  : Menu (butir menurut hak), tabel m_* (jumlah master), StockDocument, ReorderOrder, CountSchedule, Batch, HomeCards::fleet dan acCounts (GA).
 * Output       : build($section, $user) = array untuk view opening / hp.opening, atau null bila pengguna tidak berhak.
 * Dipakai oleh : OpeningController.
 * Status       : Aktif.
 */
final class Opening
{
    // bagian => [induk menu, judul, ikon, nada warna, keterangan]
    private const SECTIONS = [
        'master' => ['MST', 'Data Master', 'fa-solid fa-boxes-stacked', 'slate', 'Daftar acuan yang dipakai semua transaksi'],
        'transaksi' => ['TRX', 'Transaksi', 'fa-solid fa-right-left', 'teal', 'Dokumen stok dan pemesanan'],
        'laporan' => ['RPT', 'Laporan', 'fa-solid fa-chart-column', 'coral', 'Hanya membaca data; tidak mengubah apa pun'],
        'ga' => ['GA', 'GA', 'fa-solid fa-building-user', 'slate', 'Kendaraan, AC, dan gedung'],
        'hr' => ['HR', 'HR', 'fa-solid fa-user-tie', 'slate', 'Karyawan, bagian, rekrutmen, kedisiplinan'],
        'purchase' => ['PUR', 'Pembelian', 'fa-solid fa-cart-shopping', 'coral', 'PP, keputusan pesanan, PO, dan penerimaan pembelian'],
        'kas' => ['KEU', 'Keuangan', 'fa-solid fa-wallet', 'teal', 'Uang masuk dan keluar per kas, dilacak per sumber dana'],
    ];

    // Tabel master yang dihitung: kode modul => tabel.
    private const MASTER_TABLES = ['ITM' => 'm_item', 'SUP' => 'm_supplier', 'DEP' => 'm_department', 'RCP' => 'm_recipient', 'UOM' => 'm_unit', 'CAT' => 'm_category', 'LOC' => 'm_location'];

    private const REPORT_NOTES = ['R-CARD' => 'Riwayat satu barang', 'R-SUM' => 'Saldo dan nilai', 'R-IN' => 'Per periode', 'R-OUT' => 'Per periode', 'R-ADJ' => 'Per periode', 'R-KFLOW' => 'Buku kas per kas/rekening', 'R-KIN' => 'Per sumber dana', 'R-KOUT' => 'Per sumber dana'];

    /** Alamat layar pembuka suatu induk menu. */
    public static function url(string $section): string
    {
        return route('opening', $section);
    }

    public static function build(string $section, User $user): ?array
    {
        if (! isset(self::SECTIONS[$section])) {
            return null;
        }
        [$parent, $title, $icon, $tone, $tag] = self::SECTIONS[$section];
        $children = collect(in_array($section, ['ga', 'hr', 'purchase'], true) ? (collect(Menu::layers($user))->firstWhere('code', $parent)['children'] ?? []) : (collect(Menu::modules($user))->firstWhere('code', $parent)['children'] ?? []))
            ->reject(fn ($c) => $c['code'] === 'STB')->values();
        if ($children->isEmpty()) {
            return null;   // tanpa satu pun butir yang boleh dilihat = tanpa layar pembuka
        }
        $soon = false;   // tiap ubin yang belum dibuat ditandai sendiri (url /segera/)
        $page = ['section' => $section, 'title' => $title, 'icon' => $icon, 'tone' => $tone, 'tag' => $tag, 'soon' => $soon, 'stats' => [], 'todo' => [], 'shortcuts' => []];
        $facts = [];
        if ($section === 'master') {
            $facts = self::masters($user);
            $page['shortcuts'] = self::shortcuts($section, $user);
        } elseif ($section === 'transaksi') {
            $facts = self::transactions();
            $page['shortcuts'] = self::shortcuts($section, $user);
        } elseif ($section === 'laporan') {
            $facts = $user->canAccess('R-EXP') ? ['R-EXP' => ['n' => Locale::number(Batch::query()->nearExpiry()->count(), 0), 'note' => 'Batch hampir / lewat']] : [];
        } elseif ($section === 'ga') {
            ['facts' => $facts] = $page = array_merge($page, self::ga($user));
            unset($page['facts']);
        } elseif ($section === 'hr') {
            $facts = self::hr($user);
        } elseif ($section === 'purchase') {
            $facts = $user->canAccess('PUP') ? ['PUP' => ['n' => Locale::number(PurchaseRequest::query()->where('status', 'approval')->count(), 0), 'note' => 'menunggu persetujuan']] : [];
            if ($user->canAccess('PUK')) {
                $facts['PUK'] = ['n' => Locale::number(PurchaseRequestLine::query()->whereHas('request', fn ($q) => $q->whereIn('status', ['approved', 'partial']))->whereColumn('ordered_qty', '<', 'qty')->count(), 0), 'note' => 'baris PP menunggu dipesan'];
            }
            if ($user->canAccess('PUV')) {
                $facts['PUV'] = ['n' => Locale::number(PurchaseOrder::query()->whereIn('status', ['approved', 'partial'])->count(), 0), 'note' => 'PO menunggu barang'];
            }
            if ($user->canAccess('PUO')) {
                $facts['PUO'] = ['n' => Locale::number(PurchaseOrder::query()->where('status', 'approval')->count(), 0), 'note' => 'menunggu persetujuan'];
            }
        } elseif ($section === 'kas') {
            if ($user->canAccess('KSM')) {
                $facts['KSM'] = ['n' => Locale::number(CashIn::query()->where('status', 'approval')->count(), 0), 'note' => 'menunggu persetujuan'];
            }
            if ($user->canAccess('KSK')) {
                $facts['KSK'] = ['n' => Locale::number(CashOut::query()->where('status', 'approval')->count(), 0), 'note' => 'menunggu persetujuan'];
            }
        }

        if ($section === 'kas' && ($user->canAccess('KSM') || $user->canAccess('KSK') || $user->canAccess('R-KFLOW'))) {
            $page = array_merge($page, self::kasBoard());
        }

        $page['tiles'] = $children->map(fn ($c) => [
            'code' => $c['code'], 'label' => $c['label'], 'icon' => $c['icon'], 'url' => $c['url'], 'badge' => $c['badge'] ?? null,
            'soon' => str_contains($c['url'], '/segera/'), 'write' => $user->canWrite($c['code']),
        ] + ($facts[$c['code']] ?? ['n' => null, 'note' => self::REPORT_NOTES[$c['code']] ?? '']))->all();
        $page['write'] = $children->contains(fn ($c) => $user->canWrite($c['code']));

        return $page;
    }

    /** @return array<string, array{n: string, note: string}> */
    private static function masters(User $user): array
    {
        $out = [];
        foreach (self::MASTER_TABLES as $code => $table) {
            if (! $user->canAccess($code)) {
                continue;
            }
            $r = DB::table($table)->selectRaw('count(*) n, sum(case when is_active then 1 else 0 end) a')->first();
            $out[$code] = ['n' => Locale::number((int) $r->a, 0), 'note' => ($r->n - $r->a) > 0 ? Locale::number($r->n - $r->a, 0).' nonaktif' : ''];
        }
        if ($user->canAccess('CMP')) {
            $out['CMP'] = ['n' => '', 'note' => Company::get()['name'] ?? ''];
        }

        return $out;
    }

    /** Dashboard Kas: saldo Tunai dan Bank, masuk / keluar bulan ini, pengeluaran per Kebutuhan bulan ini, tren 6 bulan (hanya yang Disetujui). */
    private static function kasBoard(): array
    {
        $money = fn (float $n) => 'Rp '.Locale::number($n, 0);
        $accounts = CashAccount::query()->where('is_active', true)->get();
        $balance = fn (string $type) => (float) $accounts->where('type', $type)->sum(fn (CashAccount $a) => $a->currentBalance());
        $from = now()->startOfMonth();
        // Total Disetujui per bulan (6 bulan terakhir) dalam dua query, bukan satu query per bulan.
        $monthly = fn (string $model) => $model::query()->where('status', 'approved')->whereDate('doc_date', '>=', $from->copy()->subMonths(5)->toDateString())->whereDate('doc_date', '<=', $from->copy()->endOfMonth()->toDateString())
            ->get(['doc_date', 'amount'])->groupBy(fn ($r) => $r->doc_date->format('Y-m'))->map(fn ($g) => (float) $g->sum('amount'));
        $ins = $monthly(CashIn::class);
        $outs = $monthly(CashOut::class);
        $in = $ins[$from->format('Y-m')] ?? 0.0;
        $out = $outs[$from->format('Y-m')] ?? 0.0;

        $barsFrom = $from->copy()->subMonths(2);   // grafik per kebutuhan: 3 bulan terakhir (bulan ini + 2 sebelumnya)
        $bars = CashOut::query()->with('category')->where('status', 'approved')->whereDate('doc_date', '>=', $barsFrom->toDateString())->whereDate('doc_date', '<=', now()->toDateString())->get()
            ->groupBy(fn (CashOut $c) => $c->category?->name ?? 'Belum dikategorikan')->map(fn ($g) => (float) $g->sum('amount'))->sortDesc()->take(8)
            ->map(fn ($total, $name) => ['label' => $name, 'value' => $total, 'text' => $money($total)])->values()->all();

        $trend = [];
        for ($i = 5; $i >= 0; $i--) {
            $m = $from->copy()->subMonths($i);
            $trend[] = ['label' => $m->translatedFormat('M'), 'in' => $ins[$m->format('Y-m')] ?? 0.0, 'out' => $outs[$m->format('Y-m')] ?? 0.0];
        }

        return [
            'stats' => [
                ['value' => $money($balance('cash')), 'label' => 'Saldo kas tunai', 'alert' => false],
                ['value' => $money($balance('bank')), 'label' => 'Saldo rekening bank', 'alert' => false],
                ['value' => $money($in), 'label' => 'Masuk bulan ini', 'alert' => false],
                ['value' => $money($out), 'label' => 'Keluar bulan ini', 'alert' => $out > $in],
            ],
            'charts' => ['bars' => $bars, 'trend' => $trend, 'month' => $barsFrom->translatedFormat('M').' - '.$from->translatedFormat('M Y'), 'max' => max([1.0, ...array_map(fn ($t) => max($t['in'], $t['out']), $trend)])],
        ];
    }

    /** Angka HR per ubin: karyawan aktif (dan yang perlu perhatian), bagian aktif. @return array<string, array{n: string, note: string}> */
    private static function hr(User $user): array
    {
        $out = [];
        if ($user->canAccess('HRE')) {
            $staff = Employee::query()->where('is_active', true)->with(['contracts', 'docs'])->get();
            $attention = $staff->filter(fn (Employee $e) => $e->attention() !== null)->count();
            $out['HRE'] = ['n' => Locale::number($staff->count(), 0), 'note' => $attention ? $attention.' perlu perhatian' : 'aktif'];
        }
        if ($user->canAccess('HRR')) {
            $out['HRR'] = ['n' => Locale::number(Candidate::query()->where('status', 'active')->whereHas('step', fn ($q) => $q->where('kind', '!=', 'end'))->count(), 0), 'note' => 'kandidat diproses'];
        }
        if ($user->canAccess('HRD')) {
            $out['HRD'] = ['n' => Locale::number(Discipline::query()->inForce()->count(), 0), 'note' => 'SP berlaku'];
        }
        if ($user->canAccess('DEP')) {
            $out['HR-DEP'] = ['n' => Locale::number(DB::table('m_department')->where('is_active', true)->count(), 0), 'note' => 'bagian aktif'];
        }

        return $out;
    }

    /** @return array<string, array{n: string, note: string}> */
    private static function transactions(): array
    {
        $docs = StockDocument::query()->selectRaw('type, status, count(*) c, sum(case when date(doc_at) = ? then 1 else 0 end) today', [today()->toDateString()])->groupBy('type', 'status')->get();
        $sum = fn (StockDocumentType $t, array $status, string $col = 'c') => (int) $docs->where('type', $t)->whereIn('status', $status)->sum($col);
        $done = ['confirmed', 'cancel_pending'];
        $count = CountSchedule::available() ? CountSchedule::state() : null;
        $open = ReorderOrder::query()->whereNull('closed_at')->count();

        return [
            'OPN' => ['n' => Locale::number($sum(StockDocumentType::Opening, $done), 0), 'note' => 'dokumen'],
            'RCV' => ['n' => Locale::number($sum(StockDocumentType::Receiving, $done, 'today'), 0), 'note' => 'hari ini · '.$sum(StockDocumentType::Receiving, ['draft']).' Draf'],
            'ISS' => ['n' => Locale::number($sum(StockDocumentType::Issuance, $done, 'today'), 0), 'note' => 'hari ini · '.$sum(StockDocumentType::Issuance, ['draft']).' Draf'],
            'ADJ' => ['n' => Locale::number((int) $docs->whereIn('status', ['approval', 'cancel_pending'])->sum('c'), 0), 'note' => 'menunggu persetujuan'],
            'SO' => ['n' => $count ? $count['next']->format('d-m-Y') : '', 'note' => 'jadwal berikutnya'],
            'ORD' => ['n' => Locale::number($open, 0), 'note' => 'pesanan terbuka'],
        ];
    }

    /** Pintasan = aksi tulis yang paling sering, hanya bagi yang boleh menulis modulnya. @return list<array{icon: string, label: string, url: string, code: string}> */
    private static function shortcuts(string $section, User $user): array
    {
        $all = $section === 'master'
            ? [['ITM', 'fa-plus', 'Barang baru', 'products.create', []], ['SUP', 'fa-plus', 'Pemasok baru', 'suppliers.create', []]]
            : [['RCV', 'fa-truck-ramp-box', 'Penerimaan baru', 'docs.create', ['receiving']], ['ISS', 'fa-dolly', 'Pengeluaran baru', 'docs.create', ['issuance']]];

        return array_values(array_map(fn ($s) => ['code' => $s[0], 'icon' => 'fa-solid '.$s[1], 'label' => $s[2], 'url' => route($s[3], $s[4])], array_filter($all, fn ($s) => $user->canWrite($s[0]))));
    }

    /** Ringkasan GA: angka dan tindakan hanya untuk panel yang boleh dilihat (GVM kendaraan, GAM AC). */
    private static function ga(User $user): array
    {
        $stats = $todo = $shortcuts = [];
        $facts = [];
        if ($user->canAccess('GVM')) {
            $f = HomeCards::fleet();
            array_push($stats, ['value' => Locale::number($f['active'], 0), 'label' => 'Kendaraan aktif', 'alert' => false], ['value' => Locale::number($f['late'] + $f['soon'], 0), 'label' => 'Kendaraan perlu tindakan', 'alert' => $f['late'] + $f['soon'] > 0]);
            $f['late'] && $todo[] = ['danger', $f['late'], 'kendaraan Terlambat'];
            $f['soon'] && $todo[] = ['warn', $f['soon'], 'kendaraan Segera'];
            $facts['GVM'] = ['n' => Locale::number($f['active'], 0), 'note' => 'kendaraan aktif'];
        }
        if ($user->canAccess('GAM')) {
            $a = HomeCards::acCounts();
            array_push($stats, ['value' => Locale::number($a['late'], 0), 'label' => 'AC terlambat', 'alert' => $a['late'] > 0], ['value' => Locale::number($a['week'], 0), 'label' => 'AC terjadwal minggu ini', 'alert' => false]);
            $a['late'] && $todo[] = ['danger', $a['late'], 'AC terlambat dicuci'];
            $draft = $user->canAccess('GAV') || $user->canAccess('GAS') ? AcOrder::query()->where('status', 'draft')->count() : 0;
            $draft && $todo[] = ['warn', $draft, 'pesanan kunjungan AC masih Draf'];
            $facts['GAM'] = ['n' => Locale::number($a['units'], 0), 'note' => 'AC aktif'];
        }
        foreach ([['GVO', 'fa-gauge-high', 'Catat Odometer'], ['GVS', 'fa-screwdriver-wrench', 'Catat Servis'], ['GAS', 'fa-calendar-check', 'Jadwalkan AC']] as [$code, $icon, $label]) {
            if ($user->canWrite($code)) {
                $m = Modules::all()[$code];
                $shortcuts[] = ['code' => $code, 'icon' => 'fa-solid '.$icon, 'label' => $label, 'url' => route($m[4])];
            }
        }

        return ['stats' => $stats, 'todo' => $todo, 'shortcuts' => $shortcuts, 'facts' => $facts];
    }
}
