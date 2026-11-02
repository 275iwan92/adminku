{{-- REMARK
 Tujuan       : Daftar master Departemen (Grid Master): kode + nama, jumlah penerima dan dokumen pengeluaran.
 Sumber hulu  : DepartmentController@index.
 Input        : rows.
 Output       : x-dl::grid; klik 2x / Edit membuka form departemen.
 Dipakai oleh : route departments.index (menu Data Master > Departemen).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Departments'])
@section('body')
    @include('partials.flash')
    <x-dl::grid id="departments" :rows="$rows" actions="master" :new-url="route('departments.create')" :open-url="url('departments/{id}')" :toggle-url="url('departments/{id}/toggle')"
        search="Search code, name..."
        :phone-cards="['title' => 'name', 'sub' => 'code', 'meta' => ['recipients', 'documents']]"
        :columns="[
            ['key' => 'name', 'label' => 'Department', 'type' => 'identity', 'code' => 'code', 'box' => true],
            ['key' => 'recipients', 'label' => 'Recipients', 'type' => 'number'],
            ['key' => 'documents', 'label' => 'Issuance Documents', 'type' => 'number'],
            ['key' => 'employees', 'label' => 'Employees', 'type' => 'number'],
        ]"
        :stats="[['label' => 'All departments'], ['label' => 'Inactive', 'where' => ['active' => false], 'tone' => 'warn']]" />
@endsection
