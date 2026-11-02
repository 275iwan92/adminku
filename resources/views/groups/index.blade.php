{{-- REMARK
 Tujuan       : Daftar grup pengguna (Grid): kode + nama, jumlah anggota, jumlah modul. Hanya Administrator.
 Sumber hulu  : GroupController@index.
 Input        : rows.
 Output       : x-dl::grid; klik 2x / Buka membuka form grup (tempat mencentang modul).
 Dipakai oleh : route groups.index (menu Keamanan > Grup).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Groups'])
@section('body')
    @include('partials.flash')
    <x-dl::grid id="groups" :rows="$rows" actions="edit" :new-url="route('groups.create')" :open-url="url('groups/{id}')" :toggle-url="url('groups/{id}/toggle')"
        search="Search code, name..."
        :phone-cards="['title' => 'name', 'sub' => 'code', 'meta' => ['users', 'modules']]"
        :columns="[
            ['key' => 'name', 'label' => 'Group', 'type' => 'identity', 'code' => 'code', 'box' => true],
            ['key' => 'users', 'label' => 'Members', 'type' => 'number'],
            ['key' => 'modules', 'label' => 'Modules', 'type' => 'number'],
        ]"
        :stats="[['label' => 'All groups'], ['label' => 'Inactive', 'where' => ['active' => false], 'tone' => 'warn']]" />
@endsection
