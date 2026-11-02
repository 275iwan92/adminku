{{-- REMARK
 Tujuan       : Daftar Pesanan Pembelian (PO): nomor, tanggal, pemasok, kirim, total, persen diterima, status.
 Sumber hulu  : PurchaseOrderController@index.
 Input        : rows.
 Output       : x-dl::grid; klik 2x membuka PO.
 Dipakai oleh : route po.index (menu Purchase > Pesanan Pembelian (PO)).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Pesanan Pembelian'])
@section('body')
    @include('partials.flash')
    <x-dl::grid id="purchase-orders" :rows="$rows" actions="open" :open-url="url('po/{id}')"
        :new-url="auth()->user()->canWrite('PUO') ? route('po.create') : null"
        search="Cari nomor PO, pemasok..."
        :phone-cards="['title' => 'doc_no', 'sub' => 'supplier', 'meta' => ['state', 'total', 'received']]"
        :columns="[
            ['key' => 'doc_no', 'label' => 'No. PO'],
            ['key' => 'doc_date', 'label' => 'Tanggal', 'type' => 'date'],
            ['key' => 'supplier', 'label' => 'Pemasok'],
            ['key' => 'need_date', 'label' => 'Kirim', 'type' => 'date'],
            ['key' => 'total', 'label' => 'Total', 'type' => 'money'],
            ['key' => 'received', 'label' => 'Diterima'],
            ['key' => 'state', 'label' => 'Status'],
        ]"
        :filters="['state' => 'Status', 'supplier' => 'Pemasok']"
        :stats="[['label' => 'Semua PO'], ['label' => 'Perlu tindakan', 'where' => ['open' => true], 'tone' => 'warn']]" />
@endsection
