{{-- REMARK
 Tujuan       : Laporan Kedaluwarsa: barang yang masih ada stoknya dan sudah kedaluwarsa / akan kedaluwarsa dalam N hari (bawaan 30), dari yang paling dulu.
 Sumber hulu  : ReportController@expiry (i_batch.expiry_date + stock_qty). Nilai stok hanya untuk Supervisor.
 Input        : sup, days, categories, rows.
 Output       : filter (dalam berapa hari, kategori) + x-dl::report.
 Dipakai oleh : route reports.expiry (menu Laporan > Laporan Kedaluwarsa), KPI beranda.
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Expiry Report'])
@section('body')
    <x-dl::action-form method="get" :action="url()->current()" class="dl-report-filter">
        <x-dl::form.row :cols="4">
            <x-dl::field.number name="days" label="Expires within (days)" :min="0" :max="365" :value="$days" />
            <x-dl::field.select name="category_id" label="Category" :options="['' => 'Semua kategori'] + $categories" :value="request('category_id')" />
        </x-dl::form.row>
        <x-dl::action-bar>
            <x-dl::button type="submit" icon="fa-filter" label="Apply" size="sm" />
        </x-dl::action-bar>
    </x-dl::action-form>
    <x-dl::report phone-list title="Expiry Report" subtitle="Items with stock that are expired or about to expire" :company="\App\Support\Company::report()" :printed-by="raw(auth()->user()->name)" paper="a4l"
        :criteria="array_filter([
            'Expires within (days)' => raw((string) $days),
            'Category' => raw($categories[request('category_id')] ?? 'Semua kategori'),
        ])"
        group-by="status" :groups="['status' => 'Per Status', 'cat' => 'Per Category']"
        :summary="array_values(array_filter([
            ['label' => 'Barang', 'value' => count($rows)],
            ['label' => 'Sudah kedaluwarsa', 'value' => collect($rows)->where('status', 'KEDALUWARSA')->count()],
            $sup ? ['label' => 'Nilai Stok Terdampak', 'sum' => 'value'] : null,
        ]))"
        :columns="array_values(array_filter([
            ['key' => 'code', 'label' => 'Item Code'],
            ['key' => 'name', 'label' => 'Item Name', 'sub' => 'loc'],
            ['key' => 'cat', 'label' => 'Category'],
            ['key' => 'expiry', 'label' => 'Expiry Date'],
            ['key' => 'left', 'label' => 'Days Left', 'type' => 'number', 'signed' => true],
            ['key' => 'stock', 'label' => 'Stock', 'type' => 'number', 'bold' => true],
            ['key' => 'unit', 'label' => 'Unit'],
            $sup ? ['key' => 'value', 'label' => 'Stock Value', 'type' => 'money', 'sum' => true] : null,
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => ['KEDALUWARSA' => 'danger', 'HAMPIR' => 'warn'], 'rowTones' => ['KEDALUWARSA' => 'danger']],
        ]))"
        :rows="$rows" :signatures="[['Prepared by', 'Warehouse'], ['Checked by', 'Supervisor']]" source="i_batch" />
@endsection
