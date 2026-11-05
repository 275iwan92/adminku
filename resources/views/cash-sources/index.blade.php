{{-- REMARK
 Tujuan       : Daftar Sumber Dana (pilihan di Uang Masuk): nama dan jumlah Uang Masuk yang memakainya.
 Sumber hulu  : CashSourceController@index.
 Input        : rows.
 Output       : x-dl::grid; klik 2x / Edit membuka form (ubah nama, hapus).
 Dipakai oleh : route cash-sources.index (tombol Kelola Sumber Dana di Uang Masuk).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Sumber Dana'])
@section('body')
    @include('partials.flash')
    <x-dl::action-bar align="start">
        <x-dl::button class="sstock-btn-slate" variant="secondary" icon="fa-arrow-left" label="Kembali ke Uang Masuk" :href="route('cash-in.index')" />
    </x-dl::action-bar>
    <x-dl::grid id="cash-sources" :rows="$rows" actions="edit" :new-url="route('cash-sources.create')" :open-url="url('cash-sources/{id}')"
        search="Cari sumber dana..."
        :phone-cards="['title' => 'name', 'meta' => ['used']]"
        :columns="[
            ['key' => 'name', 'label' => 'Sumber Dana', 'type' => 'identity', 'box' => true],
            ['key' => 'used', 'label' => 'Dipakai di Uang Masuk', 'type' => 'number'],
        ]"
        :stats="[['label' => 'Semua sumber dana'], ['label' => 'Nonaktif', 'where' => ['active' => false], 'tone' => 'warn']]" />
@endsection
