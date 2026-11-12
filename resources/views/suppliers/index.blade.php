{{-- REMARK
 Tujuan       : Daftar master Supplier (Grid Master): kode + nama, telepon kantor, kontak + HP, satu kolom Kota / Negara, alamat jalan.
 Sumber hulu  : SupplierController@index (suppliers + GeoArea).
 Input        : rows.
 Output       : x-dl2::grid (DevLib 2); klik 2x / Edit membuka form supplier.
 Dipakai oleh : route suppliers.index (menu Master Data > Suppliers).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Pemasok'])
@section('body')
    @include('partials.flash')
    <x-dl2::grid id="suppliers" :config="[
        'rows' => $rows, 'actions' => 'edit', 'activeKey' => 'active', 'newUrl' => route('suppliers.create'), 'newLabel' => '+ Baru',
        'openUrl' => url('suppliers/{id}'),
        'labels' => ['search' => 'Search code, name, contact, city...'],
        'phoneCards' => ['title' => 'name', 'sub' => 'code', 'meta' => ['contact', 'mobile', 'term', 'place']],
        'columns' => [
            ['key' => 'name', 'label' => 'Supplier', 'type' => 'identity', 'code' => 'code', 'box' => false],
            ['key' => 'phone', 'label' => 'Company Phone'],
            ['key' => 'contact', 'label' => 'Contact'],
            ['key' => 'mobile', 'label' => 'Contact Mobile'],
            ['key' => 'email', 'label' => 'Email'],
            ['key' => 'term', 'label' => 'Payment Term'],
            ['key' => 'place', 'label' => 'City, Country'],
            ['key' => 'address', 'label' => 'Street Address', 'hidden' => true],
        ],
        'stats' => [['label' => 'All suppliers'], ['label' => 'Inactive', 'where' => ['active' => false], 'tone' => 'warn']],
    ]" />
@endsection
