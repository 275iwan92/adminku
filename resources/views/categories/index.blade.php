{{-- REMARK
 Tujuan       : Daftar master Kategori barang (Grid Master): awalan kode + nama, jumlah barang.
 Sumber hulu  : CategoryController@index.
 Input        : rows.
 Output       : x-dl2::grid (DevLib 2); klik 2x / Edit membuka form kategori.
 Dipakai oleh : route categories.index (menu Data Master > Kategori, Supervisor).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Categories'])
@section('body')
    @include('partials.flash')
    <div style="max-width: 820px">
    <x-dl2::grid id="categories" :config="[
        'rows' => $rows, 'actions' => 'edit', 'activeKey' => 'active', 'newUrl' => route('categories.create'), 'newLabel' => '+ Baru',
        'openUrl' => url('categories/{id}'),
        'labels' => ['search' => 'Search code or name...'],
        'phoneCards' => ['title' => 'name', 'sub' => 'code', 'meta' => ['items']],
        'columns' => [
            ['key' => 'name', 'label' => 'Category', 'type' => 'identity', 'code' => 'code', 'hue' => 'hue', 'box' => false],
            ['key' => 'items', 'label' => 'Items using it', 'type' => 'badge', 'tones' => ['0' => 'warning']],
        ],
        'stats' => [['label' => 'All categories'], ['label' => 'Inactive', 'where' => ['active' => false], 'tone' => 'warn']],
    ]" />
    </div>
@endsection
