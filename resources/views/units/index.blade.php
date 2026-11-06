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
    @php
        $tones = ['Hitungan' => 'violet', 'Berat' => 'warn', 'Volume' => 'info', 'Panjang' => 'success', 'Luas' => 'neutral', 'Lainnya' => 'neutral'];
        $hasConv = collect($rows)->contains(fn ($r) => $r['conv'] !== '');   // kolom Konversi disembunyikan selama belum ada satu pun konversi
    @endphp
    <div style="max-width: 760px">
    <x-dl::grid id="units" :rows="$rows" actions="edit" :new-url="route('units.create')" :open-url="url('units/{id}')" :toggle-url="url('units/{id}/toggle')"
        search="Search code or name..."
        :phone-cards="['title' => 'name', 'sub' => 'code', 'badge' => 'kind', 'tones' => $tones, 'meta' => $hasConv ? ['conv', 'items'] : ['items']]"
        :columns="[
            ['key' => 'name', 'label' => 'Unit', 'type' => 'identity', 'code' => 'code', 'avatar' => false, 'box' => false],
            ['key' => 'kind', 'label' => 'Type', 'type' => 'badge', 'tones' => $tones],
            ...($hasConv ? [['key' => 'conv', 'label' => 'Conversions']] : []),
            ['key' => 'items', 'label' => 'Items using it', 'type' => 'badge', 'toneKey' => 'itemsTone'],
        ]"
        :stats="[['label' => 'All units'], ['label' => 'Inactive', 'where' => ['active' => false], 'tone' => 'warn']]" />
    </div>
@endsection
