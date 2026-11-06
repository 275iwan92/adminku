{{-- REMARK
 Tujuan       : Daftar master Kas / Rekening (Grid Master): nama, jenis, no. rekening, saldo berjalan.
 Sumber hulu  : CashAccountController@index.
 Input        : rows.
 Output       : x-dl::grid; klik 2x / Edit membuka form Kas/Rekening.
 Dipakai oleh : route cash-accounts.index (tombol Setup di Uang Masuk / Uang Keluar).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Kas / Rekening'])
@section('body')
    @include('partials.flash')
    <x-dl::action-bar align="start">
        <x-dl::button class="sstock-btn-slate" variant="secondary" icon="fa-arrow-left" label="Kembali ke Uang Masuk" :href="route('cash-in.index')" />
    </x-dl::action-bar>
    <x-dl::grid id="cash-accounts" :rows="$rows" actions="edit" :new-url="route('cash-accounts.create')" :open-url="url('cash-accounts/{id}')" :toggle-url="url('cash-accounts/{id}/toggle')"
        search="Cari nama, no. rekening..."
        :phone-cards="['title' => 'name', 'sub' => 'type', 'meta' => ['account_no', 'balance']]"
        :columns="[
            ['key' => 'name', 'label' => 'Nama', 'type' => 'identity', 'code' => 'type', 'avatar' => false, 'box' => false],
            ['key' => 'account_no', 'label' => 'No. Rekening'],
            ['key' => 'balance', 'label' => 'Saldo Berjalan', 'type' => 'money'],
        ]"
        :stats="[['label' => 'Semua Kas / Rekening'], ['label' => 'Nonaktif', 'where' => ['active' => false], 'tone' => 'warn']]" />
@endsection
