{{-- REMARK
 Tujuan       : Daftar Kebutuhan (pilihan di Uang Keluar): nama dan jumlah Uang Keluar yang memakainya.
 Sumber hulu  : CashPurposeController@index.
 Input        : rows.
 Output       : x-dl::grid; klik 2x / Edit membuka form (ubah nama, hapus).
 Dipakai oleh : route cash-purposes.index (tombol Kelola Kebutuhan di Uang Keluar).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Kebutuhan'])
@section('body')
    @include('partials.flash')
    <x-dl::action-bar align="start">
        <x-dl::button class="sstock-btn-slate" variant="secondary" icon="fa-arrow-left" label="Kembali ke Uang Keluar" :href="route('cash-out.index')" />
    </x-dl::action-bar>
    <x-dl::grid id="cash-purposes" :rows="$rows" actions="edit" :new-url="route('cash-purposes.create')" :open-url="url('cash-purposes/{id}')"
        search="Cari kebutuhan..."
        :phone-cards="['title' => 'name', 'meta' => ['used']]"
        :columns="[
            ['key' => 'name', 'label' => 'Kebutuhan', 'type' => 'identity', 'box' => true],
            ['key' => 'used', 'label' => 'Dipakai di Uang Keluar', 'type' => 'number'],
        ]"
        :stats="[['label' => 'Semua kebutuhan']]" />
@endsection
