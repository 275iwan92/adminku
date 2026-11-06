{{-- REMARK
 Tujuan       : Daftar master Kategori barang (Grid Master): awalan kode + nama, jumlah barang.
 Sumber hulu  : CategoryController@index.
 Input        : rows.
 Output       : x-dl::grid; klik 2x / Edit membuka form kategori.
 Dipakai oleh : route categories.index (menu Data Master > Kategori, Supervisor).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Categories'])
@section('body')
    @php $rows = collect($rows)->map(fn ($r) => $r + ['itemsTone' => $r['items'] ? 'neutral' : 'warn'])->all(); @endphp
    @include('partials.flash')
    <div style="max-width: 820px">
    <x-dl::grid id="categories" :rows="$rows" actions="edit" :new-url="route('categories.create')" :open-url="url('categories/{id}')" :toggle-url="url('categories/{id}/toggle')"
        search="Search code or name..."
        :phone-cards="['title' => 'name', 'sub' => 'code', 'meta' => ['items']]"
        :columns="[
            ['key' => 'name', 'label' => 'Category', 'type' => 'identity', 'code' => 'code', 'hue' => 'hue', 'box' => false],
            ['key' => 'items', 'label' => 'Items using it', 'type' => 'badge', 'toneKey' => 'itemsTone'],
        ]"
        :stats="[['label' => 'All categories'], ['label' => 'Inactive', 'where' => ['active' => false], 'tone' => 'warn']]" />
    </div>
@endsection
