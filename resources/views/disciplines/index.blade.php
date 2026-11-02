{{-- REMARK
 Tujuan       : Daftar penerbitan Kedisiplinan (modul HRD): nomor surat, tanggal, karyawan, bagian, jenis (SP 1 / SP 2 / SP 3 / PHK), berlaku s/d, status (Aktif / Diganti / Kedaluwarsa / Dicabut / Terbit) dan alasan.
 Sumber hulu  : DisciplineController@index.
 Input        : rows.
 Output       : x-dl::grid; klik 2x membuka penerbitan (detail, cetak, cabut).
 Dipakai oleh : route disciplines.index (menu HR > Kedisiplinan).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Kedisiplinan'])
@section('body')
    @include('partials.flash')
    <x-dl::grid id="disciplines" :rows="$rows" actions="open" :open-url="url('disciplines/{id}')"
        :new-url="auth()->user()->canWrite('HRD') ? route('disciplines.create') : null"
        search="Cari nomor surat, karyawan, alasan..."
        :phone-cards="['title' => 'employee', 'sub' => 'doc_no', 'meta' => ['type', 'state', 'department']]"
        :columns="[
            ['key' => 'doc_no', 'label' => 'No. Surat'],
            ['key' => 'issued_on', 'label' => 'Tanggal', 'type' => 'date'],
            ['key' => 'employee', 'label' => 'Karyawan', 'type' => 'identity', 'code' => 'code', 'box' => true],
            ['key' => 'department', 'label' => 'Bagian'],
            ['key' => 'type', 'label' => 'Jenis'],
            ['key' => 'until_on', 'label' => 'Berlaku s/d', 'type' => 'date'],
            ['key' => 'state', 'label' => 'Status'],
            ['key' => 'reason', 'label' => 'Alasan'],
        ]"
        :filters="['type' => 'Jenis', 'state' => 'Status', 'department' => 'Bagian']"
        :stats="[['label' => 'Semua penerbitan'], ['label' => 'Berlaku sekarang', 'where' => ['now' => true], 'tone' => 'warn']]" />
@endsection
