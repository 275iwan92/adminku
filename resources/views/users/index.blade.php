{{-- REMARK
 Tujuan       : Daftar pengguna (Grid Master): nama + username, e-mail, peran. Hanya Administrator.
 Sumber hulu  : UserController@index.
 Input        : rows.
 Output       : x-dl2::grid; klik 2x / Edit membuka form pengguna.
 Dipakai oleh : route users.index (menu Keamanan > Pengguna).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Users'])
@section('body')
    @include('partials.flash')
    <x-dl2::grid id="users" :rows="$rows" actions="edit" :new-url="route('users.create')" :open-url="url('users/{id}')" :toggle-url="url('users/{id}/toggle')"
        search="Search name, username, email..."
        :phone-cards="['title' => 'name', 'sub' => 'username', 'meta' => ['email', 'role', 'group']]"
        :columns="[
            ['key' => 'name', 'label' => 'User', 'type' => 'identity', 'code' => 'username', 'box' => false],
            ['key' => 'email', 'label' => 'Email'],
            ['key' => 'role', 'label' => 'Role'],
            ['key' => 'group', 'label' => 'Group'],
        ]"
        :stats="[['label' => 'All users'], ['label' => 'Inactive', 'where' => ['active' => false], 'tone' => 'warn']]" />
@endsection
