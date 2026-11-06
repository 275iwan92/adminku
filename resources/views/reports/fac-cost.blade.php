{{-- REMARK
 Tujuan       : Laporan Biaya Perawatan (GA Gedung dan Aset): Perintah Kerja Disetujui dalam periode, dikelompokkan per aset / gedung / kategori; biaya tenaga, material, total.
 Sumber hulu  : FacReportController@cost.
 Input        : from, to, rows, category, building, categories, buildings.
 Output       : filter (periode + gedung + kategori) + x-dl::report (cetak / ekspor).
 Dipakai oleh : route reports.fac-cost (dashboard Perawatan Gedung dan Aset > Laporan Biaya).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Laporan Biaya Perawatan'])
@section('body')
    <x-report-filter :from="$from" :to="$to">
        <x-dl::field.select name="building" label="Gedung" :options="['' => 'Semua gedung'] + $buildings" :value="$building ?: ''" />
        <x-dl::field.select name="category" label="Kategori aset" :options="['' => 'Semua kategori'] + $categories" :value="$category ?: ''" />
    </x-report-filter>
    <x-dl::report phone-list title="Laporan Biaya Perawatan" subtitle="Perintah Kerja yang disetujui (Selesai), menurut tanggal selesai" :company="\App\Support\Company::report()" :printed-by="raw(auth()->user()->name)" paper="a4l"
        :criteria="array_filter(['Periode' => raw(Devlib\Locale::d($from).' — '.Devlib\Locale::d($to)), 'Gedung' => raw($buildings[$building] ?? 'Semua gedung'), 'Kategori' => raw($categories[$category] ?? 'Semua kategori')])"
        group-by="asset" :groups="['asset' => 'Per Aset', 'building' => 'Per Gedung', 'category' => 'Per Kategori']"
        :summary="[
            ['label' => 'Jumlah Perintah Kerja', 'value' => $count],
            ['label' => 'Biaya Tenaga', 'sum' => 'labor'],
            ['label' => 'Biaya Material', 'sum' => 'material'],
            ['label' => 'Total Biaya', 'sum' => 'total'],
        ]"
        :columns="[
            ['key' => 'date', 'label' => 'Tanggal'],
            ['key' => 'no', 'label' => 'No. WO'],
            ['key' => 'title', 'label' => 'Pekerjaan'],
            ['key' => 'asset', 'label' => 'Aset'],
            ['key' => 'building', 'label' => 'Gedung'],
            ['key' => 'category', 'label' => 'Kategori'],
            ['key' => 'handler', 'label' => 'Dikerjakan oleh'],
            ['key' => 'labor', 'label' => 'Tenaga', 'type' => 'money', 'sum' => true],
            ['key' => 'material', 'label' => 'Material', 'type' => 'money', 'sum' => true],
            ['key' => 'total', 'label' => 'Total', 'type' => 'money', 'sum' => true],
        ]"
        :rows="$rows" :signatures="[['Dibuat oleh', 'Admin'], ['Diperiksa oleh', 'Supervisor']]" source="g_fac_order" />
@endsection
