{{-- REMARK
 Tujuan       : Summary Stock per periode (dari tanggal sampai tanggal): saldo awal, masuk, keluar, penyesuaian, saldo akhir per item, status Reorder Point terhadap saldo akhir, dan untuk Supervisor nilai valuasi + total aset persediaan.
 Sumber hulu  : ReportController@summary (items + rm_stockcard). Data Entry tidak melihat biaya / nilai.
 Input        : sup, from, to, categories, rows.
 Output       : filter (periode, kategori, hanya reorder, termasuk nonaktif) + x-dl::report.
 Dipakai oleh : route reports.summary (menu Reports > Summary Stock).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Summary Stock'])
@section('body')
    <x-report-filter :from="$from" :to="$to">
        <x-dl::field.select name="category_id" label="Category" :options="['' => 'Semua kategori'] + $categories" :value="request('category_id')" />
        <x-dl::field.checkbox name="only_reorder" label="Filter" text="Only items at / below reorder point" :value="request()->boolean('only_reorder')" />
        <x-dl::field.checkbox name="inactive" label="Inactive" text="Include inactive items" :value="request()->boolean('inactive')" />
    </x-report-filter>
    <x-dl::report phone-list title="Summary Stock" subtitle="Stock position and movement per period" :company="\App\Support\Company::report()" :printed-by="raw(auth()->user()->name)" paper="a4l"
        :criteria="array_filter([
            'Period' => raw(Devlib\Locale::d($from).' — '.Devlib\Locale::d($to)),
            'Category' => raw($categories[request('category_id')] ?? 'Semua kategori'),
            'Scope' => raw(request()->boolean('only_reorder') ? 'Hanya barang pesan ulang' : 'Semua barang'),
            'Valuation' => $sup ? 'Rata-rata bergerak' : null,
        ])"
        group-by="cat" :groups="['cat' => 'Per Category', 'status' => 'Per Status']"
        :summary="array_values(array_filter([
            ['label' => 'Barang', 'value' => count($rows)],
            ['label' => 'Di / bawah titik pesan ulang', 'value' => collect($rows)->where('status', 'PESAN ULANG')->count()],
            $sup ? ['label' => 'Total Nilai Stok', 'sum' => 'value'] : null,
        ]))"
        :columns="array_values(array_filter([
            ['key' => 'code', 'label' => 'Item Code'],
            ['key' => 'name', 'label' => 'Item Name', 'sub' => 'loc'],
            ['key' => 'unit', 'label' => 'Unit'],
            ['key' => 'open', 'label' => 'Opening', 'type' => 'number'],
            ['key' => 'in', 'label' => 'In', 'type' => 'number', 'signed' => true],
            ['key' => 'out', 'label' => 'Out', 'type' => 'number'],
            ['key' => 'adj', 'label' => 'Adjustment', 'type' => 'number', 'signed' => true],
            ['key' => 'end', 'label' => 'Closing', 'type' => 'number', 'bold' => true],
            ['key' => 'rop', 'label' => 'Reorder Point', 'wrap' => true, 'type' => 'number'],
            ['key' => 'lead', 'label' => 'Lead Time (d)', 'wrap' => true, 'type' => 'number'],
            ['key' => 'lead_auto', 'label' => 'Lead Time Otomatis (d)', 'wrap' => true, 'type' => 'number'],
            $sup ? ['key' => 'cost', 'label' => 'Avg Cost', 'type' => 'money'] : null,
            $sup ? ['key' => 'value', 'label' => 'Stock Value', 'type' => 'money', 'sum' => true, 'bold' => true] : null,
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => ['AMAN' => 'success', 'PESAN ULANG' => 'danger'], 'rowTones' => ['PESAN ULANG' => 'danger']],
            ['key' => 'order_supplier', 'label' => 'Supplier Pesanan', 'wrap' => true],
            ['key' => 'order_qty', 'label' => 'Jumlah Dipesan', 'wrap' => true],
            ['key' => 'order', 'label' => 'Info Pemesanan', 'wrap' => true],
        ]))"
        :rows="$rows" :signatures="[['Prepared by', 'Warehouse'], ['Checked by', 'Supervisor']]" source="items, rm_stockcard" />
@endsection
