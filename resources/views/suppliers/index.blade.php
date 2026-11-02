{{-- REMARK
 Tujuan       : Daftar master Supplier (Grid Master): kode + nama, telepon kantor, kontak + HP, satu kolom Kota / Negara, alamat jalan.
 Sumber hulu  : SupplierController@index (suppliers + GeoArea).
 Input        : rows.
 Output       : x-dl::grid; klik 2x / Edit membuka form supplier.
 Dipakai oleh : route suppliers.index (menu Master Data > Suppliers).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Pemasok'])
@section('body')
    @include('partials.flash')
    <x-dl::grid id="suppliers" :rows="$rows" actions="edit" :new-url="route('suppliers.create')" :open-url="url('suppliers/{id}')" :toggle-url="url('suppliers/{id}/toggle')"
        search="Search code, name, contact, city..."
        :phone-cards="['title' => 'name', 'sub' => 'code', 'meta' => ['phone', 'contact', 'mobile', 'place']]"
        :columns="[
            ['key' => 'name', 'label' => 'Supplier', 'type' => 'identity', 'code' => 'code', 'box' => true],
            ['key' => 'phone', 'label' => 'Company Phone'],
            ['key' => 'contact', 'label' => 'Contact'],
            ['key' => 'mobile', 'label' => 'Contact Mobile'],
            ['key' => 'place', 'label' => 'City, Country'],
            ['key' => 'address', 'label' => 'Street Address', 'visible' => false],
        ]"
        :stats="[['label' => 'All suppliers'], ['label' => 'Inactive', 'where' => ['active' => false], 'tone' => 'warn']]" />
@endsection
