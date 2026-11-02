{{-- REMARK
 Tujuan       : Daftar master Ruang AC (Grid Master, modul GAR): kode ruang + nama dalam kotak berwarna menurut area, area, jumlah AC, PIC (nama, WhatsApp, e-mail).
 Sumber hulu  : AcRoomController@index.
 Input        : rows.
 Output       : x-dl::grid; klik 2x / Edit membuka form ruang; saklar Aktif / Nonaktif.
 Dipakai oleh : route ac-rooms.index (menu GA > Ruang AC).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Ruang AC'])
@section('body')
    @include('partials.flash')
    <x-dl::grid id="ac-rooms" :rows="$rows" actions="master" :new-url="route('ac-rooms.create')" :open-url="url('ac-rooms/{id}')" :toggle-url="url('ac-rooms/{id}/toggle')"
        search="Cari kode, ruang, area, PIC..."
        :phone-cards="['title' => 'name', 'sub' => 'code', 'meta' => ['area', 'pic']]"
        :columns="[
            ['key' => 'name', 'label' => 'Ruang', 'type' => 'identity', 'code' => 'code', 'hue' => 'hue', 'box' => true],
            ['key' => 'area', 'label' => 'Area'],
            ['key' => 'units', 'label' => 'AC', 'type' => 'integer'],
            ['key' => 'pic', 'label' => 'PIC'],
            ['key' => 'phone', 'label' => 'WhatsApp PIC'],
            ['key' => 'email', 'label' => 'E-mail PIC', 'visible' => false],
        ]"
        :stats="[['label' => 'Semua ruang'], ['label' => 'Nonaktif', 'where' => ['active' => false], 'tone' => 'warn']]" />
@endsection
