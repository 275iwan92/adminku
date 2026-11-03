{{-- REMARK
 Tujuan       : Daftar master Unit AC (Grid Master, modul GAK): kode aset + ruang dalam kotak berwarna menurut area, area, merek / tipe / PK, tanggal pasang, cuci rutin dan servis besar terakhir (awal).
 Sumber hulu  : AcUnitController@index.
 Input        : rows.
 Output       : x-dl::grid; klik 2x / Edit membuka form unit.
 Dipakai oleh : route ac-units.index (menu GA > Master AC).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Master AC'])
@section('body')
    @include('partials.flash')
    @include('partials.back-to-hub', ['hub' => 'ac'])
    <x-dl::grid id="ac-units" :rows="$rows" actions="edit" :new-url="route('ac-units.create')" :open-url="url('ac-units/{id}')" :toggle-url="url('ac-units/{id}/toggle')"
        search="Cari kode aset, ruang, area, merek..."
        :phone-cards="['title' => 'asset_code', 'sub' => 'room', 'meta' => ['type', 'area']]"
        :columns="[
            ['key' => 'asset_code', 'label' => 'Unit AC', 'type' => 'identity', 'code' => 'room', 'hue' => 'hue', 'box' => true],
            ['key' => 'area', 'label' => 'Area'],
            ['key' => 'type', 'label' => 'Merek / tipe / PK'],
            ['key' => 'installed', 'label' => 'Dipasang'],
            ['key' => 'wash', 'label' => 'Cuci awal', 'visible' => false],
            ['key' => 'major', 'label' => 'Servis besar awal', 'visible' => false],
        ]"
        :stats="[['label' => 'Semua AC'], ['label' => 'Nonaktif', 'where' => ['active' => false], 'tone' => 'warn']]" />
@endsection
