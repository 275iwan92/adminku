{{-- REMARK
 Tujuan       : Laporan Pengeluaran (Uang Keluar): dikelompokkan per Sumber Dana (lewat Uang Masuk yang ditaut).
 Sumber hulu  : CashReportController@disbursements (k_cash_out + k_cash_in).
 Input        : from, to, rows, accountId, accounts, status, source, sources.
 Output       : filter (periode + kas + sumber dana + status) + x-dl2::report.
 Dipakai oleh : route reports.cash-disbursements (Beranda Kas > Laporan Pengeluaran).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Laporan Pengeluaran'])
@section('body')
    <x-report-filter :from="$from" :to="$to">
        <x-dl2::field.select name="cash_account_id" label="Kas" :options="['' => 'Semua kas'] + $accounts" :value="request('cash_account_id')" />
        <x-dl2::field.select name="source" label="Sumber Dana" :options="['' => 'Semua sumber dana'] + $sources" :value="$source" />
        <x-dl2::field.select name="status" label="Status" :options="['' => 'Semua'] + \App\Models\CashOut::STATUS" :value="$status" />
    </x-report-filter>
    <x-dl2::report phone-list title="Laporan Pengeluaran" subtitle="Uang Keluar per Kebutuhan (kategori); bisa diganti per Kas / Rekening atau per Sumber Dana" :company="\App\Support\Company::report()" :printed-by="raw(auth()->user()->name)" paper="a4l"
        :criteria="array_filter(['Periode' => raw(Devlib2\Locale::d($from).' — '.Devlib2\Locale::d($to)), 'Kas' => raw($accounts[request('cash_account_id')] ?? 'Semua kas'), 'Sumber Dana' => raw($source !== '' ? $source : 'Semua sumber dana'), 'Status' => raw(\App\Models\CashOut::STATUS[$status] ?? 'Semua')])"
        group-by="category" :groups="['category' => 'Per Kebutuhan', 'channel' => 'Per Kas / Rekening (Tunai dan tiap rekening bank)', 'source_name' => 'Per Sumber Dana']"
        :summary="[
            ['label' => 'Jumlah Uang Keluar', 'value' => count($rows)],
            ['label' => 'Keluar Tunai', 'value' => $cash],
            ['label' => 'Keluar Bank', 'value' => $bank],
            ['label' => 'Total Pengeluaran', 'sum' => 'amount'],
        ]"
        :columns="[
            ['key' => 'date', 'label' => 'Tanggal'],
            ['key' => 'doc', 'label' => 'No. UK'],
            ['key' => 'cash_in', 'label' => 'Ditaut Ke'],
            ['key' => 'account', 'label' => 'Kas Asal'],
            ['key' => 'source_name', 'label' => 'Sumber Dana'],
            ['key' => 'purpose', 'label' => 'Keterangan'],
            ['key' => 'amount', 'label' => 'Jumlah', 'type' => 'money', 'sum' => true],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => ['Draf' => 'neutral', 'Diajukan' => 'warn', 'Disetujui' => 'success', 'Ditolak' => 'danger', 'Dibatalkan' => 'danger']],
        ]"
        :rows="$rows" :signatures="[['Dibuat oleh', 'Admin'], ['Diperiksa oleh', 'Supervisor']]" source="k_cash_out, k_cash_in" />
@endsection
