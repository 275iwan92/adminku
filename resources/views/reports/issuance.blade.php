{{-- REMARK
 Tujuan       : Laporan Pengeluaran Barang per periode (tanggal, jam, penerima, departemen, keperluan, item, qty, user).
 Sumber hulu  : ReportController@issuance (rm_stock_line tipe OUT). Biaya & jumlah hanya untuk Supervisor.
 Input        : sup, from, to, rows.
 Output       : filter (periode + penerima / departemen) + x-dl::report.
 Dipakai oleh : route reports.issuance (menu Reports > Issuance Report).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Issuance Report'])
@section('body')
    <x-report-filter :from="$from" :to="$to">
        <x-dl::field.text name="who" label="Recipient / Department" :value="request('who')" :case-toggle="false" />
    </x-report-filter>
    <x-dl::report phone-list title="Issuance Report" subtitle="Goods issued for maintenance / ATK" :company="\App\Support\Company::report()" :printed-by="raw(auth()->user()->name)" paper="a4l"
        :criteria="['Period' => raw(Devlib\Locale::d($from).' — '.Devlib\Locale::d($to)), 'Recipient / Department' => raw(request('who') ?: 'Semua')]"
        group-by="dept" :groups="['dept' => 'Per Department', 'party' => 'Per Recipient', 'month' => 'Per Month']"
        :summary="array_values(array_filter([['label' => 'Baris dikeluarkan', 'value' => count($rows)], $sup ? ['label' => 'Total Biaya Keluar', 'sum' => 'amount'] : null]))"
        :columns="array_values(array_filter([
            ['key' => 'date', 'label' => 'Date & Time'],
            ['key' => 'doc', 'label' => 'Document No.', 'sub' => 'reason'],
            ['key' => 'party', 'label' => 'Recipient'],
            ['key' => 'dept', 'label' => 'Department'],
            ['key' => 'item', 'label' => 'Item', 'sub' => 'code'],
            ['key' => 'qty', 'label' => 'Qty', 'type' => 'number'],
            ['key' => 'unit', 'label' => 'Unit'],
            $sup ? ['key' => 'cost', 'label' => 'Unit Cost', 'type' => 'money'] : null,
            $sup ? ['key' => 'amount', 'label' => 'Cost', 'type' => 'money', 'sum' => true, 'bold' => true] : null,
            ['key' => 'user', 'label' => 'Entered by'],
        ]))"
        :rows="$rows" :signatures="[['Prepared by', 'Warehouse'], ['Received by', 'Recipient'], ['Checked by', 'Supervisor']]" source="rm_stock_line (OUT)" />
@endsection
