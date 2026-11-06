{{-- REMARK
 Tujuan       : Daftar master Area / Gedung AC (Grid Master, modul GAA): nama + kode dalam kotak berwarna, jumlah ruang, ada tidaknya gambar denah.
 Sumber hulu  : AcAreaController@index.
 Input        : rows.
 Output       : x-dl::grid; klik 2x / Edit membuka form area.
 Dipakai oleh : route ac-areas.index (menu GA > Area dan Gedung AC).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Area dan Gedung AC'])
@section('body')
    @php $rows = collect($rows)->map(fn ($r) => $r + ['roomsTone' => $r['rooms'] ? 'neutral' : 'warn'])->all(); @endphp
    @include('partials.flash')
    @include('partials.back-to-hub', ['hub' => 'ac'])
    <div style="max-width: 820px">
    <x-dl::grid id="ac-areas" :rows="$rows" actions="edit" :new-url="route('ac-areas.create')" :open-url="url('ac-areas/{id}')" :toggle-url="url('ac-areas/{id}/toggle')"
        search="Cari kode atau nama..."
        :phone-cards="['title' => 'name', 'sub' => 'code', 'meta' => ['rooms', 'plan']]"
        :columns="[
            ['key' => 'name', 'label' => 'Area / Gedung', 'type' => 'identity', 'code' => 'code', 'hue' => 'hue', 'box' => false],
            ['key' => 'rooms', 'label' => 'Ruang', 'type' => 'badge', 'toneKey' => 'roomsTone'],
            ['key' => 'plan', 'label' => 'Denah'],
        ]"
        :stats="[['label' => 'Semua area'], ['label' => 'Nonaktif', 'where' => ['active' => false], 'tone' => 'warn']]" />
    </div>
@endsection
