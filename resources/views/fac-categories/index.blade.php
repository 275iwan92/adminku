{{-- REMARK
 Tujuan       : Daftar master Kategori Aset (modul FAC): nama + kode dalam kotak berwarna, jumlah aset.
 Sumber hulu  : FacCategoryController@index.
 Input        : rows.
 Output       : x-dl2::grid; klik 2x / Edit membuka form kategori.
 Dipakai oleh : route fac-categories.index (Setup Perawatan Gedung dan Aset).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Kategori Aset'])
@section('body')
    @include('partials.flash')
    @include('partials.back-to-hub', ['hub' => 'fac'])
    <x-dl2::grid id="fac-categories" :rows="$rows" actions="edit" :new-url="route('fac-categories.create')" :open-url="url('fac-categories/{id}')" :toggle-url="url('fac-categories/{id}/toggle')"
        search="Cari kode atau nama kategori..."
        :phone-cards="['title' => 'name', 'sub' => 'code', 'meta' => ['assets']]"
        :columns="[
            ['key' => 'name', 'label' => 'Kategori', 'type' => 'identity', 'code' => 'code', 'hue' => 'hue', 'box' => false],
            ['key' => 'assets', 'label' => 'Aset', 'type' => 'integer'],
        ]"
        :stats="[['label' => 'Semua kategori'], ['label' => 'Nonaktif', 'where' => ['active' => false], 'tone' => 'warn']]" />
@endsection
