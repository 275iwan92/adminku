{{-- REMARK
 Tujuan       : Daftar Karyawan (Grid Master, modul HRE): kode + nama dengan jabatan, bagian, status kerja, tanggal masuk, status (Bekerja / Cuti / Dirumahkan / Keluar: alasan) dan kolom Perhatian (kontrak atau dokumen hampir habis, data belum lengkap). NIK tidak tampil di daftar.
 Sumber hulu  : EmployeeController@index.
 Input        : rows.
 Output       : x-dl::grid; klik 2x / Edit membuka form karyawan.
 Dipakai oleh : route employees.index (menu HR > Karyawan).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Karyawan'])
@section('body')
    @include('partials.flash')
    <x-dl::grid id="employees" :rows="$rows" actions="edit" :new-url="route('employees.create')" :open-url="url('employees/{id}')"
        search="Cari kode, nama, jabatan, bagian..."
        :phone-cards="['title' => 'name', 'sub' => 'code', 'meta' => ['position', 'department', 'status', 'attention']]"
        :columns="[
            ['key' => 'name', 'label' => 'Karyawan', 'type' => 'identity', 'code' => 'code', 'box' => true],
            ['key' => 'position', 'label' => 'Jabatan'],
            ['key' => 'department', 'label' => 'Bagian'],
            ['key' => 'employment', 'label' => 'Status kerja'],
            ['key' => 'join_date', 'label' => 'Masuk', 'type' => 'date'],
            ['key' => 'status', 'label' => 'Status'],
            ['key' => 'attention', 'label' => 'Perhatian'],
        ]"
        :stats="[['label' => 'Semua karyawan'], ['label' => 'Perlu perhatian', 'where' => ['attn' => true], 'tone' => 'warn'], ['label' => 'Keluar', 'where' => ['active' => false]]]" />
@endsection
