{{-- REMARK
 Tujuan       : Daftar master Vendor AC (Grid Master, modul GAE): nama + kode dalam kotak berwarna, kontak, nomor WhatsApp, e-mail.
 Sumber hulu  : AcVendorController@index.
 Input        : rows.
 Output       : x-dl::grid; klik 2x / Edit membuka form vendor.
 Dipakai oleh : route ac-vendors.index (menu GA > Vendor AC).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Vendor AC'])
@section('body')
    @include('partials.flash')
    @include('partials.back-to-hub', ['hub' => 'ac'])
    <x-dl::grid id="ac-vendors" :rows="$rows" actions="edit" :new-url="route('ac-vendors.create')" :open-url="url('ac-vendors/{id}')" :toggle-url="url('ac-vendors/{id}/toggle')"
        search="Cari kode, nama, kontak..."
        :phone-cards="['title' => 'name', 'sub' => 'code', 'meta' => ['contact', 'phone']]"
        :columns="[
            ['key' => 'name', 'label' => 'Vendor', 'type' => 'identity', 'code' => 'code', 'hue' => 'hue', 'box' => false],
            ['key' => 'contact', 'label' => 'Kontak'],
            ['key' => 'phone', 'label' => 'WhatsApp'],
            ['key' => 'email', 'label' => 'E-mail'],
        ]"
        :stats="[['label' => 'Semua vendor'], ['label' => 'Nonaktif', 'where' => ['active' => false], 'tone' => 'warn']]" />
@endsection
