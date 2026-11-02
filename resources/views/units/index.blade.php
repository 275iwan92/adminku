{{-- REMARK
 Tujuan       : Daftar master Satuan (Grid Master) beserta jumlah item pemakainya.
 Sumber hulu  : UnitController@index (units, items_count).
 Input        : rows.
 Output       : x-dl::grid; klik 2x / Edit membuka form satuan.
 Dipakai oleh : route units.index (menu Master Data > Units, Supervisor).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Units'])
@section('body')
    @include('partials.flash')
    {{-- Lebar dibatasi supaya kolom Unit, Conversions, dan jumlah item tidak terpisah jauh di layar lebar. --}}
    <div style="max-width: 820px">
    <x-dl::grid id="units" :rows="$rows" actions="edit" :new-url="route('units.create')" :open-url="url('units/{id}')" :toggle-url="url('units/{id}/toggle')"
        search="Search code or name..."
        :phone-cards="['title' => 'name', 'sub' => 'code', 'meta' => ['kind', 'conv', 'items']]"
        :columns="[
            ['key' => 'name', 'label' => 'Unit', 'type' => 'identity', 'code' => 'code', 'hue' => 'hue', 'box' => true],
            ['key' => 'kind', 'label' => 'Type'],
            ['key' => 'conv', 'label' => 'Conversions'],
            ['key' => 'items', 'label' => 'Items using it', 'type' => 'integer'],
        ]"
        :stats="[['label' => 'All units'], ['label' => 'Inactive', 'where' => ['active' => false], 'tone' => 'warn']]" />
    </div>
@endsection
