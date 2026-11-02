{{-- REMARK
 Tujuan       : Laporan Penerimaan Barang Masuk per periode (tanggal, jam, supplier, no. referensi, item, qty, harga beli, user).
 Sumber hulu  : ReportController@receiving (rm_stock_line tipe IN). Harga & jumlah hanya untuk Supervisor.
 Input        : sup, from, to, rows, suppliers.
 Output       : filter + x-dl::report.
 Dipakai oleh : route reports.receiving (menu Reports > Receiving Report).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Receiving Report'])
@section('body')
    <x-report-filter :from="$from" :to="$to">
        <x-dl::field.select name="supplier_id" label="Supplier" :options="['' => 'Semua pemasok'] + $suppliers" :value="request('supplier_id')" />
    </x-report-filter>
    <x-dl::report phone-list title="Receiving Report" subtitle="Goods received from suppliers" :company="\App\Support\Company::report()" :printed-by="raw(auth()->user()->name)" paper="a4l"
        :criteria="['Period' => raw(Devlib\Locale::d($from).' — '.Devlib\Locale::d($to)), 'Supplier' => raw($suppliers[request('supplier_id')] ?? 'Semua pemasok')]"
        group-by="party" :groups="['party' => 'Per Supplier', 'month' => 'Per Month']"
        :summary="array_values(array_filter([['label' => 'Baris diterima', 'value' => count($rows)], $sup ? ['label' => 'Total Nilai Pembelian', 'sum' => 'amount'] : null]))"
        :columns="array_values(array_filter([
            ['key' => 'date', 'label' => 'Date & Time'],
            ['key' => 'doc', 'label' => 'Document No.', 'sub' => 'ref'],
            ['key' => 'party', 'label' => 'Supplier'],
            ['key' => 'recipient', 'label' => 'Recipient'],
            ['key' => 'dept', 'label' => 'Department'],
            ['key' => 'item', 'label' => 'Item', 'sub' => 'code'],
            ['key' => 'qty', 'label' => 'Qty (stock unit)', 'type' => 'number'],
            ['key' => 'unit', 'label' => 'Unit'],
            ['key' => 'entered', 'label' => 'Received as'],
            $sup ? ['key' => 'cost', 'label' => 'Purchase Price', 'type' => 'money'] : null,
            $sup ? ['key' => 'amount', 'label' => 'Amount', 'type' => 'money', 'sum' => true, 'bold' => true] : null,
            ['key' => 'user', 'label' => 'Entered by'],
        ]))"
        :rows="$rows" :signatures="[['Prepared by', 'Warehouse'], ['Checked by', 'Supervisor']]" source="rm_stock_line (IN)" />
@endsection
