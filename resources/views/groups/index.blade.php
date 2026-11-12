{{-- REMARK
 Tujuan       : Daftar grup pengguna (Grid): kode + nama, jumlah anggota, jumlah modul. Hanya Administrator.
 Sumber hulu  : GroupController@index.
 Input        : rows.
 Output       : x-dl2::grid (DevLib 2); klik 2x / Buka membuka form grup (tempat mencentang modul).
 Dipakai oleh : route groups.index (menu Keamanan > Grup).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Groups'])
@section('body')
    @include('partials.flash')
    <x-dl2::grid id="groups" :config="[
        'rows' => $rows, 'actions' => 'edit', 'activeKey' => 'active', 'newUrl' => route('groups.create'), 'newLabel' => '+ Baru',
        'openUrl' => url('groups/{id}'),
        'labels' => ['search' => 'Search code, name...'],
        'phoneCards' => ['title' => 'name', 'sub' => 'code', 'meta' => ['users', 'modules']],
        'columns' => [
            ['key' => 'name', 'label' => 'Group', 'type' => 'identity', 'code' => 'code', 'avatar' => false, 'box' => false],
            ['key' => 'users', 'label' => 'Members', 'type' => 'badge', 'tones' => ['0' => 'warning']],
            ['key' => 'modules', 'label' => 'Modules', 'type' => 'badge', 'tones' => ['0' => 'warning']],
        ],
        'stats' => [['label' => 'All groups'], ['label' => 'Inactive', 'where' => ['active' => false], 'tone' => 'warn']],
    ]" />
@endsection
