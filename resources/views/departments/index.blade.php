{{-- REMARK
 Tujuan       : Daftar master Departemen (Grid Master): kode + nama, jumlah penerima dan dokumen pengeluaran.
 Sumber hulu  : DepartmentController@index.
 Input        : rows.
 Output       : x-dl2::grid (awal tampilan kartu pita warna, bisa ke tabel); klik 2x / Edit membuka form departemen.
 Dipakai oleh : route departments.index (menu Data Master > Departemen).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Departments'])
@section('body')
    @php $rows = collect($rows)->map(fn ($r) => $r + ['recipientsTone' => $r['recipients'] ? 'neutral' : 'warn', 'documentsTone' => $r['documents'] ? 'neutral' : 'warn', 'employeesTone' => $r['employees'] ? 'neutral' : 'warn'])->all(); @endphp
    @include('partials.flash')
    <x-dl2::grid id="departments" :rows="$rows" actions="edit" :new-url="route('departments.create')" :open-url="url('departments/{id}')" :toggle-url="url('departments/{id}/toggle')"
        search="Search code, name..."
        :cards="['style' => 'band', 'start' => true, 'title' => 'name', 'sub' => 'code', 'icon' => 'icon', 'hue' => 'hue', 'big' => 'employees', 'meta' => ['recipients', 'documents']]"
        :columns="[
            ['key' => 'name', 'label' => 'Department', 'type' => 'identity', 'code' => 'code', 'avatar' => false, 'box' => false],
            ['key' => 'recipients', 'label' => 'Recipients', 'type' => 'badge', 'toneKey' => 'recipientsTone'],
            ['key' => 'documents', 'label' => 'Issuance Documents', 'type' => 'badge', 'toneKey' => 'documentsTone'],
            ['key' => 'employees', 'label' => 'Employees', 'type' => 'badge', 'toneKey' => 'employeesTone'],
        ]"
        :stats="[['label' => 'All departments'], ['label' => 'Inactive', 'where' => ['active' => false], 'tone' => 'warn']]" />
@endsection
