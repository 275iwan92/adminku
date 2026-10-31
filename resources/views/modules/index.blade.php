{{-- REMARK
 Tujuan       : Daftar semua modul (layar menu) aplikasi: kode, nama, induk menu, peran minimal, dan grup yang boleh membukanya. Hanya baca.
 Sumber hulu  : ModuleController@index (Modules::all + sys_user_group_module).
 Input        : rows.
 Output       : x-dl::grid tanpa aksi; pengaturan akses ada di menu Grup.
 Dipakai oleh : route modules.index (menu Keamanan > Daftar Modul).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Modules'])
@section('body')
    <x-dl::grid id="modules" :rows="$rows" actions="none" search="Search code, module..."
        :phone-cards="['title' => 'name', 'sub' => 'code', 'meta' => ['parent', 'level', 'groups']]"
        :columns="[
            ['key' => 'name', 'label' => 'Module', 'type' => 'identity', 'code' => 'code', 'avatar' => false],
            ['key' => 'parent', 'label' => 'Menu'],
            ['key' => 'level', 'label' => 'Minimum Role'],
            ['key' => 'groups', 'label' => 'Groups With Access'],
        ]"
        :stats="[['label' => 'All modules']]" />
@endsection
