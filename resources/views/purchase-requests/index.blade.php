{{-- REMARK
 Tujuan       : Daftar Permintaan Pembelian (PP): nomor, tanggal, bagian peminta, pemohon, dibutuhkan, jumlah barang, estimasi total, status.
 Sumber hulu  : PurchaseRequestController@index.
 Input        : rows.
 Output       : x-dl2::grid; klik 2x membuka PP.
 Dipakai oleh : route pr.index (menu Purchase > Permintaan Pembelian).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Permintaan Pembelian'])
@section('body')
    @include('partials.flash')
    <x-dl2::grid id="purchase-requests" :rows="$rows" actions="open" :open-url="url('pr/{id}')"
        :new-url="auth()->user()->canWrite('PUP') ? route('pr.create') : null"
        search="Cari nomor PP, bagian, pemohon..."
        :phone-cards="['title' => 'doc_no', 'sub' => 'department', 'meta' => ['requester', 'state', 'total']]"
        :columns="[
            ['key' => 'doc_no', 'label' => 'No. PP'],
            ['key' => 'doc_date', 'label' => 'Tanggal', 'type' => 'date'],
            ['key' => 'department', 'label' => 'Bagian peminta'],
            ['key' => 'requester', 'label' => 'Pemohon'],
            ['key' => 'need_date', 'label' => 'Dibutuhkan', 'type' => 'date'],
            ['key' => 'items', 'label' => 'Barang', 'type' => 'number'],
            ['key' => 'total', 'label' => 'Estimasi total', 'type' => 'money'],
            ['key' => 'state', 'label' => 'Status'],
        ]"
        :filters="['state' => 'Status', 'department' => 'Bagian peminta']"
        :stats="[['label' => 'Semua PP'], ['label' => 'Perlu tindakan', 'where' => ['open' => true], 'tone' => 'warn']]" />
@endsection
