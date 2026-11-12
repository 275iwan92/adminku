{{-- REMARK
 Tujuan       : Laporan Arus Kas: buku kas per kas/rekening — saldo awal periode, baris Uang Masuk/Uang Keluar Disetujui kronologis, saldo akhir. Bisa digabung jadi satu buku "Semua Kas".
 Sumber hulu  : CashReportController@cashFlow (m_cash_account, k_cash_in, k_cash_out status Disetujui).
 Input        : from, to, rows, accountId, accounts, totalOpen, totalIn, totalOut, totalClose.
 Output       : filter (periode + kas) + x-dl2::report.
 Dipakai oleh : route reports.cash-flow (Beranda Kas > Laporan Arus Kas).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Laporan Arus Kas'])
@section('body')
    <x-report-filter :from="$from" :to="$to">
        <x-dl2::field.select name="cash_account_id" label="Kas" :options="['' => 'Semua kas'] + $accounts" :value="request('cash_account_id')" />
    </x-report-filter>
    <x-dl2::report phone-list title="Laporan Arus Kas" subtitle="Buku kas per kas/rekening — saldo awal, baris masuk/keluar kronologis, saldo akhir" :company="\App\Support\Company::report()" :printed-by="raw(auth()->user()->name)" paper="a4l"
        :criteria="['Periode' => raw(Devlib2\Locale::d($from).' — '.Devlib2\Locale::d($to)), 'Kas' => raw($accounts[request('cash_account_id')] ?? 'Semua kas')]"
        group-by="cash_account" :groups="['cash_account' => 'Per Kas / Rekening', 'all' => 'Gabungan Semua Kas']"
        :summary="[
            ['label' => 'Saldo Awal', 'value' => $totalOpen],
            ['label' => 'Total Masuk', 'value' => $totalIn],
            ['label' => 'Total Keluar', 'value' => $totalOut],
            ['label' => 'Saldo Akhir', 'value' => $totalClose],
            ['label' => 'Saldo Akhir Tunai', 'value' => $closeCash],
            ['label' => 'Saldo Akhir Bank', 'value' => $closeBank],
        ]"
        :columns="[
            ['key' => 'date', 'label' => 'Tanggal'],
            ['key' => 'doc', 'label' => 'No. Dokumen'],
            ['key' => 'uraian', 'label' => 'Uraian'],
            ['key' => 'masuk', 'label' => 'Masuk', 'type' => 'money', 'sum' => true],
            ['key' => 'keluar', 'label' => 'Keluar', 'type' => 'money', 'sum' => true],
            ['key' => 'saldo', 'label' => 'Saldo Berjalan', 'type' => 'money', 'bold' => true],
        ]"
        :rows="$rows" :signatures="[['Dibuat oleh', 'Admin / Bendahara'], ['Diperiksa oleh', 'Supervisor']]" source="m_cash_account, k_cash_in, k_cash_out" />
@endsection
