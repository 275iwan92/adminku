{{-- REMARK
 Tujuan       : Daftar Penerimaan Pembelian (PV): nomor, tanggal, PO, pemasok, surat jalan, nomor RCV di Stok, status.
 Sumber hulu  : PurchaseReceiptController@index.
 Input        : rows.
 Output       : x-dl2::grid; klik 2x membuka PV.
 Dipakai oleh : route pv.index (menu Purchase > Penerimaan Pembelian (PV)).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Penerimaan Pembelian'])
@section('body')
    @include('partials.flash')
    <x-dl2::grid id="purchase-receipts" :rows="$rows" actions="open" :open-url="url('pv/{id}')"
        :new-url="auth()->user()->canWrite('PUV') ? route('pv.create') : null"
        search="Cari nomor PV, PO, pemasok, surat jalan..."
        :phone-cards="['title' => 'doc_no', 'sub' => 'supplier', 'meta' => ['order', 'state', 'rcv']]"
        :columns="[
            ['key' => 'doc_no', 'label' => 'No. PV'],
            ['key' => 'doc_date', 'label' => 'Tanggal', 'type' => 'date'],
            ['key' => 'order', 'label' => 'PO'],
            ['key' => 'supplier', 'label' => 'Pemasok'],
            ['key' => 'ref_no', 'label' => 'Surat jalan'],
            ['key' => 'rcv', 'label' => 'No. RCV (Stok)'],
            ['key' => 'state', 'label' => 'Status'],
        ]"
        :filters="['state' => 'Status', 'supplier' => 'Pemasok']"
        :stats="[['label' => 'Semua PV']]" />
@endsection
