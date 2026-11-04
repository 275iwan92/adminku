{{-- REMARK
 Tujuan       : Laporan Penerimaan (Uang Masuk): dikelompokkan per Sumber Dana — jumlah diterima, terpakai, sisa.
 Sumber hulu  : CashReportController@receipts (k_cash_in).
 Input        : from, to, rows, status, source, sources.
 Output       : filter (periode + sumber dana + status) + x-dl::report.
 Dipakai oleh : route reports.cash-receipts (Beranda Kas > Laporan Penerimaan).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Laporan Penerimaan'])
@section('body')
    <x-report-filter :from="$from" :to="$to">
        <x-dl::field.select name="source" label="Sumber Dana" :options="['' => 'Semua sumber dana'] + $sources" :value="$source" />
        <x-dl::field.select name="status" label="Status" :options="['' => 'Semua'] + \App\Models\CashIn::STATUS" :value="$status" />
    </x-report-filter>
    <x-dl::report phone-list title="Laporan Penerimaan" subtitle="Uang Masuk dikelompokkan per Sumber Dana — jumlah diterima, terpakai, sisa" :company="\App\Support\Company::report()" :printed-by="raw(auth()->user()->name)" paper="a4l"
        :criteria="array_filter(['Periode' => raw(Devlib\Locale::d($from).' — '.Devlib\Locale::d($to)), 'Sumber Dana' => raw($source !== '' ? $source : 'Semua sumber dana'), 'Status' => raw(\App\Models\CashIn::STATUS[$status] ?? 'Semua')])"
        group-by="source_name" :groups="['source_name' => 'Per Sumber Dana']"
        :summary="[
            ['label' => 'Jumlah Uang Masuk', 'value' => count($rows)],
            ['label' => 'Total Diterima', 'sum' => 'amount'],
            ['label' => 'Total Terpakai', 'sum' => 'used'],
            ['label' => 'Total Sisa', 'sum' => 'remaining'],
        ]"
        :columns="[
            ['key' => 'date', 'label' => 'Tanggal'],
            ['key' => 'doc', 'label' => 'No. UM'],
            ['key' => 'account', 'label' => 'Kas Tujuan'],
            ['key' => 'amount', 'label' => 'Jumlah', 'type' => 'money', 'sum' => true],
            ['key' => 'used', 'label' => 'Terpakai', 'type' => 'money', 'sum' => true],
            ['key' => 'remaining', 'label' => 'Sisa', 'type' => 'money', 'sum' => true],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => ['Draf' => 'neutral', 'Diajukan' => 'warn', 'Disetujui' => 'success', 'Ditolak' => 'danger', 'Dibatalkan' => 'danger']],
        ]"
        :rows="$rows" :signatures="[['Dibuat oleh', 'Admin'], ['Diperiksa oleh', 'Supervisor']]" source="k_cash_in" />
@endsection
