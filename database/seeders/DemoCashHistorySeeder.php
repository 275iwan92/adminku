<?php

namespace Database\Seeders;

use App\Models\CashAccount;
use App\Models\CashIn;
use App\Models\CashOut;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;

/* REMARK
 * Tujuan       : Data demo modul Kas: 3 kas/rekening (Kas Tunai, Bank BCA, Bank Mandiri), 7 Uang Masuk dengan sumber dana berbeda tersebar ~3 bulan terakhir (5 Disetujui, 1 Diajukan, 1 Draf),
 *                dan 8 Uang Keluar tertaut bermacam-macam "untuk apa" (6 Disetujui, 1 Diajukan, 1 Ditolak) — sebagian Uang Masuk sisa saldonya banyak, sebagian hampir habis, supaya variatif untuk laporan nanti.
 *                Dibuat lewat rute aplikasi sendiri (cash-in.*, cash-out.*) dengan waktu sistem digeser ke tanggal transaksi, seperti DemoPurchaseHistorySeeder, jadi nomor dokumen, tanggal, dan status persis pemakaian nyata. Idempoten: dilewati bila Kas Tunai sudah ada.
 *                Grup SUPERVISOR dan ADMINDATA sudah otomatis punya akses modul Kas lewat migration 2026_12_03_100000_create_admin_data_role_group.php (menggranti semua modul selain Keamanan dari Modules::all() saat ini), jadi tidak perlu hak tambahan di sini.
 * Sumber hulu  : User (admin, supervisor, entry), rute cash-in.*, cash-out.*.
 * Output       : m_cash_account (3 baris), k_cash_in (7), k_cash_out (10: 8 tertaut Uang Masuk, 2 tanpa tautan).
 * Dipakai oleh : DatabaseSeeder hanya bila config sstock.demo (produk demo AdminKU), setelah DemoPurchaseHistorySeeder; ikut adminku:reset-demo.
 * Status       : Aktif (data demo).
 */
class DemoCashHistorySeeder extends Seeder
{
    public function run(): void
    {
        if (CashAccount::where('name', 'Kas Tunai')->exists()) {
            return;   // idempoten
        }
        $http = new class
        {
            use InteractsWithAuthentication, MakesHttpRequests;

            public $app;
        };
        $http->app = app();
        $prefix = rtrim((string) parse_url(url('/'), PHP_URL_PATH), '/');
        $http->withServerVariables(['SCRIPT_NAME' => $prefix.'/index.php', 'SCRIPT_FILENAME' => public_path('index.php'), 'PHP_SELF' => $prefix.'/index.php']);
        $http->withHeaders(['Sec-Fetch-Site' => 'same-origin']);
        config(['session.driver' => 'array']);

        $admin = User::where('username', 'admin')->firstOrFail();
        $boss = User::where('username', 'spv')->firstOrFail();
        $entry = User::where('username', 'adm')->firstOrFail();

        $tunai = CashAccount::create(['name' => 'Kas Tunai', 'type' => 'cash', 'opening_balance' => 2_000_000, 'is_active' => true]);
        $bca = CashAccount::create(['name' => 'Bank BCA', 'type' => 'bank', 'bank_name' => 'Bank BCA', 'account_no' => '1234567890', 'account_holder' => 'PT XYZ', 'opening_balance' => 15_000_000, 'is_active' => true]);
        $mandiri = CashAccount::create(['name' => 'Bank Mandiri', 'type' => 'bank', 'bank_name' => 'Bank Mandiri', 'account_no' => '9876543210', 'account_holder' => 'PT XYZ', 'opening_balance' => 8_000_000, 'is_active' => true]);

        $base = today();
        try {
            $um1 = $this->cashIn($http, $entry, $boss, $bca, 'Donatur Bapak Hadi', 10_000_000, $base->copy()->subDays(85), 'approved');
            $this->cashOut($http, $entry, $boss, $um1, 5_000_000, 'Operasional kantor', $base->copy()->subDays(80), 'approved', desc: 'Sewa tempat kegiatan');
            $um2 = $this->cashIn($http, $entry, $boss, $tunai, 'Donatur umum', 1_500_000, $base->copy()->subDays(70), 'approved');
            $this->cashOut($http, $entry, $boss, $um2, 500_000, 'Pembelian bahan pendukung', $base->copy()->subDays(65), 'approved', desc: 'ATK');
            $this->cashOut($http, $entry, $boss, $um1, 3_000_000, 'Konsumsi', $base->copy()->subDays(60), 'approved', desc: 'Konsumsi acara');
            $um3 = $this->cashIn($http, $entry, $boss, $mandiri, 'Penjualan barang bekas', 6_000_000, $base->copy()->subDays(55), 'approved');
            $this->cashOut($http, $entry, $boss, $um3, 1_000_000, 'Operasional kantor', $base->copy()->subDays(50), 'approved', desc: 'Listrik dan air kantor');
            $um4 = $this->cashIn($http, $entry, $boss, $bca, 'Pendapatan operasional', 3_000_000, $base->copy()->subDays(40), 'approved');
            $this->cashOut($http, $entry, $boss, $um3, 1_000_000, 'Transportasi', $base->copy()->subDays(35), 'approved', desc: 'Bensin dan parkir');
            $this->cashOut($http, $entry, $boss, $um4, 2_900_000, 'Pembelian bahan pendukung', $base->copy()->subDays(30), 'approved', desc: 'ATK dan cetak');
            $this->cashIn($http, $entry, $boss, $tunai, 'Donatur umum', 800_000, $base->copy()->subDays(25), 'approved');
            $this->cashOut($http, $entry, $boss, $um1, 1_500_000, 'Lainnya', $base->copy()->subDays(20), 'approval', desc: 'Dokumentasi kegiatan');
            $this->cashOut($http, $entry, $boss, $um3, 500_000, 'Transportasi', $base->copy()->subDays(15), 'rejected', 'Melebihi anggaran triwulan ini.', desc: 'Perjalanan dinas');
            $this->cashIn($http, $entry, $boss, $mandiri, 'Penjualan barang bekas', 4_000_000, $base->copy()->subDays(10), 'approval');
            $this->freeCashOut($http, $entry, $boss, $tunai, 300_000, 'Konsumsi', $base->copy()->subDays(12), 'Snack rapat bulanan, dari kas tunai');
            $this->freeCashOut($http, $entry, $boss, $bca, 750_000, 'Pembelian bahan pendukung', $base->copy()->subDays(6), 'Alat kebersihan, transfer rekening BCA');
            $this->cashIn($http, $entry, $boss, $bca, 'Donatur Bapak Hadi', 2_000_000, $base->copy()->subDays(3), 'draft');
        } finally {
            Carbon::setTestNow();
        }
    }

    // Uang Masuk: dibuat draf, lalu Confirm; approved juga disetujui 'approval' berhenti di diajukan; draft berhenti di draf.
    private function cashIn($http, User $creator, User $approver, CashAccount $account, string $source, int $amount, Carbon $date, string $final): CashIn
    {
        Carbon::setTestNow($date->copy()->setTime(9, 0));
        $payload = ['doc_date' => $date->toDateString(), 'cash_account_id' => $account->id, 'source_name' => $source, 'amount' => $amount];
        $http->actingAs($creator)->post(route('cash-in.store'), $payload);
        $cashIn = CashIn::query()->latest('id')->firstOrFail();
        if ($final === 'draft') {
            return $cashIn;
        }
        $http->put(route('cash-in.update', $cashIn), ['_action' => 'confirm'] + $payload);
        if ($final === 'approved') {
            Carbon::setTestNow($date->copy()->setTime(14, 0));
            $http->actingAs($approver)->put(route('cash-in.update', $cashIn), ['_action' => 'approve']);
        }

        return $cashIn->fresh();
    }

    // Uang Keluar TANPA tautan Uang Masuk: Kas Asal dipilih sendiri (Tunai memakai kas tunai aktif, bank memakai rekeningnya), dibatasi saldo kas.
    private function freeCashOut($http, User $creator, User $approver, CashAccount $account, int $amount, string $purpose, Carbon $date, string $desc): CashOut
    {
        Carbon::setTestNow($date->copy()->setTime(10, 0));
        $payload = ['doc_date' => $date->toDateString(), 'cash_in_id' => null, 'cash_kind' => $account->type === 'bank' ? 'bank' : 'cash', 'cash_account_id' => $account->id, 'amount' => $amount, 'purpose' => $purpose, 'description' => $desc];
        $http->actingAs($creator)->post(route('cash-out.store'), $payload);
        $cashOut = CashOut::query()->latest('id')->firstOrFail();
        $http->put(route('cash-out.update', $cashOut), ['_action' => 'confirm'] + $payload);
        Carbon::setTestNow($date->copy()->setTime(15, 30));
        $http->actingAs($approver)->put(route('cash-out.update', $cashOut), ['_action' => 'approve']);

        return $cashOut->fresh();
    }

    // Uang Keluar: dibuat draf tertaut ke Uang Masuk, Confirm, lalu Approve atau Reject (alasan wajib).
    private function cashOut($http, User $creator, User $approver, CashIn $cashIn, int $amount, string $purpose, Carbon $date, string $final, ?string $reason = null, ?string $desc = null): CashOut
    {
        Carbon::setTestNow($date->copy()->setTime(9, 30));
        $payload = ['doc_date' => $date->toDateString(), 'cash_in_id' => $cashIn->id, 'amount' => $amount, 'purpose' => $purpose, 'description' => $desc];
        $http->actingAs($creator)->post(route('cash-out.store'), $payload);
        $cashOut = CashOut::query()->latest('id')->firstOrFail();
        $http->put(route('cash-out.update', $cashOut), ['_action' => 'confirm'] + $payload);
        if ($final === 'approved' || $final === 'rejected') {
            Carbon::setTestNow($date->copy()->setTime(15, 0));
            $http->actingAs($approver)->put(route('cash-out.update', $cashOut), $final === 'approved' ? ['_action' => 'approve'] : ['_action' => 'reject', '_reason' => $reason]);
        }

        return $cashOut->fresh();
    }
}
