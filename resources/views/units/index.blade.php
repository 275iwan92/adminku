{{-- REMARK
 Tujuan       : Daftar master Satuan (Grid Master) beserta jumlah item pemakainya.
 Sumber hulu  : UnitController@index (units, items_count).
 Input        : rows.
 Output       : x-dl2::grid (DevLib 2); klik 2x / Edit membuka form satuan.
 Dipakai oleh : route units.index (menu Master Data > Units, Supervisor).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Units'])
@section('body')
    @include('partials.flash')
    {{-- Lebar dibatasi supaya kolom Unit, Conversions, dan jumlah item tidak terpisah jauh di layar lebar. --}}
    @php
        $tones = ['Hitungan' => 'info', 'Berat' => 'warning', 'Volume' => 'secondary', 'Panjang' => 'success', 'Luas' => 'dark', 'Lainnya' => 'dark'];
        $hasConv = collect($rows)->contains(fn ($r) => $r['conv'] !== '');   // kolom Konversi disembunyikan selama belum ada satu pun konversi
    @endphp
    <div style="max-width: 760px">
    <x-dl2::grid id="units" :config="[
        'rows' => $rows, 'actions' => 'edit', 'activeKey' => 'active', 'newUrl' => route('units.create'), 'newLabel' => '+ Baru',
        'openUrl' => url('units/{id}'),
        'labels' => ['search' => 'Search code or name...'],
        'phoneCards' => ['title' => 'name', 'sub' => 'code', 'badge' => 'kind', 'tones' => $tones, 'meta' => $hasConv ? ['conv', 'items'] : ['items']],
        'columns' => [
            ['key' => 'name', 'label' => 'Unit', 'type' => 'identity', 'code' => 'code', 'avatar' => false, 'box' => false],
            ['key' => 'kind', 'label' => 'Type', 'type' => 'badge', 'tones' => $tones],
            ...($hasConv ? [['key' => 'conv', 'label' => 'Conversions']] : []),
            ['key' => 'items', 'label' => 'Items using it', 'type' => 'badge', 'tones' => ['0' => 'warning']],
        ],
        'stats' => [['label' => 'All units'], ['label' => 'Inactive', 'where' => ['active' => false], 'tone' => 'warn']],
    ]" />
    </div>
@endsection
