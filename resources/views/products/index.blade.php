{{-- REMARK
 Tujuan       : Daftar master Barang (Grid Master): satu baris per produk dengan total stok, stok tersedia (batch belum kedaluwarsa), kedaluwarsa terdekat, dan status pesan ulang.
 Sumber hulu  : ProductController@index (produk + jumlah batch). Kolom harga & nilai hanya untuk Supervisor.
 Input        : rows, sup.
 Output       : x-dl::grid; klik 2x / Edit membuka form barang; + New Record untuk semua pengguna, saklar aktif hanya Supervisor.
 Dipakai oleh : route products.index (menu Master Data > Barang).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Items'])
@section('body')
    @include('partials.flash')
    <x-dl::grid id="products" :rows="$rows" :actions="$sup ? 'edit' : 'worklist'" new-record="+ New Record" :new-url="route('products.create')"
        :open-url="url('products/{id}')" :toggle-url="$sup ? url('products/{id}/toggle') : null" search="Search code, name, location..." row-tone="tone"
        :columns="array_values(array_filter([
            ['key' => 'name', 'label' => 'Item', 'type' => 'identity', 'code' => 'code', 'hue' => 'hue', 'box' => false],
            ['key' => 'category', 'label' => 'Category'],
            ['key' => 'unit', 'label' => 'Unit'],
            ['key' => 'location', 'label' => 'Location'],
            ['key' => 'batches', 'label' => 'Batches', 'type' => 'integer'],
            ['key' => 'expiry', 'label' => 'Nearest Expiry'],
            ['key' => 'stock', 'label' => 'Stock', 'type' => 'number', 'decimals' => 2],
            ['key' => 'available', 'label' => 'Available Stock', 'type' => 'number', 'decimals' => 2],
            ['key' => 'rop', 'label' => 'Reorder Point', 'type' => 'number', 'decimals' => 2],
            ['key' => 'roq', 'label' => 'Reorder Qty', 'type' => 'number', 'decimals' => 2, 'visible' => false],
            ['key' => 'lead', 'label' => 'Lead Time (days)', 'type' => 'integer'],
            ['key' => 'level', 'label' => 'Level', 'type' => 'badge', 'tones' => ['AMAN' => 'success', 'PESAN ULANG' => 'danger']],
            $sup ? ['key' => 'price', 'label' => 'Unit Price', 'type' => 'money', 'decimals' => 2, 'visible' => false] : null,
            $sup ? ['key' => 'value', 'label' => 'Stock Value', 'type' => 'money', 'decimals' => 2, 'sum' => true] : null,
        ]))"
        :stats="[
            ['label' => 'All items'],
            ['label' => 'Reorder', 'where' => ['level' => 'PESAN ULANG'], 'tone' => 'danger'],
            ['label' => 'Inactive', 'where' => ['active' => false], 'tone' => 'warn'],
        ]"
        :filters="['category' => 'Category', 'unit' => 'Unit']"
        {{-- Tampilan HP: daftar kartu (nama + kode, lencana Level, stok + satuan, lokasi, kedaluwarsa); desktop tetap tabel. --}}
        :phone-cards="['title' => 'name', 'sub' => 'code', 'badge' => 'level', 'tones' => ['AMAN' => 'success', 'PESAN ULANG' => 'danger'], 'meta' => ['stock', 'unit', 'available', 'location', 'expiry']]" />
@endsection
