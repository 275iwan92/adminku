{{-- REMARK
 Tujuan       : Daftar master Penerima barang (Grid Master): nama + departemen bawaan, jumlah dokumen pengeluaran.
 Sumber hulu  : RecipientController@index.
 Input        : rows.
 Output       : x-dl::grid; klik 2x / Edit membuka form penerima.
 Dipakai oleh : route recipients.index (menu Data Master > Penerima).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Recipients'])
@section('body')
    @include('partials.flash')
    <x-dl::grid id="recipients" :rows="$rows" actions="edit" :new-url="route('recipients.create')" :open-url="url('recipients/{id}')" :toggle-url="url('recipients/{id}/toggle')"
        search="Search name, department..."
        :phone-cards="['title' => 'name', 'sub' => 'department', 'meta' => ['documents']]"
        :columns="[
            ['key' => 'name', 'label' => 'Recipient', 'type' => 'identity', 'code' => 'department', 'box' => true],
            ['key' => 'documents', 'label' => 'Issuance Documents', 'type' => 'number'],
        ]"
        :stats="[['label' => 'All recipients'], ['label' => 'Inactive', 'where' => ['active' => false], 'tone' => 'warn']]" />
@endsection
