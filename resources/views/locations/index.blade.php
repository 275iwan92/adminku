{{-- REMARK
 Tujuan       : Daftar master Lokasi Penyimpanan (Grid Master) beserta jumlah barang pemakainya.
 Sumber hulu  : LocationController@index.
 Input        : rows.
 Output       : x-dl::grid; klik 2x / Edit membuka form lokasi.
 Dipakai oleh : route locations.index (menu Data Master > Lokasi Penyimpanan, Supervisor).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Storage Locations'])
@section('body')
    @include('partials.flash')
    <div style="max-width: 820px">
    <x-dl::grid id="locations" :rows="$rows" actions="edit" :new-url="route('locations.create')" :open-url="url('locations/{id}')" :toggle-url="url('locations/{id}/toggle')"
        search="Search code or name..."
        :phone-cards="['title' => 'name', 'sub' => 'code', 'meta' => ['items']]"
        :columns="[
            ['key' => 'name', 'label' => 'Location', 'type' => 'identity', 'code' => 'code', 'hue' => 'hue', 'box' => true],
            ['key' => 'items', 'label' => 'Items using it', 'type' => 'integer'],
        ]"
        :stats="[['label' => 'All locations'], ['label' => 'Inactive', 'where' => ['active' => false], 'tone' => 'warn']]" />
    </div>
@endsection
