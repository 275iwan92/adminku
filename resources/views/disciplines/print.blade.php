{{-- REMARK
 Tujuan       : Halaman cetak surat SP 1 / SP 2 / SP 3 / PHK (A4 potret, x-dl2::print-sheet): kop perusahaan, judul, nomor, identitas karyawan, isi, tanda tangan penerbit dan karyawan.
 Sumber hulu  : DisciplineController@print (data = App\Support\DisciplinePrint).
 Input        : discipline, data (prop print-sheet).
 Output       : x-dl2::action-bar (Kembali + Print / PDF), x-dl2::print-sheet.
 Dipakai oleh : route disciplines.print; tombol Cetak di detail penerbitan.
 Status       : Aktif.
--}}
@extends('layout', ['title' => $data['title']])
@section('body')
    <x-dl2::box>
        <x-dl2::action-bar>
            <x-dl2::button class="sstock-btn-slate" variant="secondary" icon="back" label="Kembali" :href="route('disciplines.show', $discipline)" />
            <x-dl2::doc-share target="#print-sheet" type="print-discipline" :doc-id="$discipline->id" :number="$discipline->doc_no" :title="$data['title']" :email="false" orientation="portrait" />
        </x-dl2::action-bar>
        <x-dl2::print-sheet id="print-sheet" :title="$data['title']" :code="$data['code']" :info="$data['info']" :sections="$data['sections']" :signatures="$data['signatures']" :note="$data['note']" :landscape="false" />
    </x-dl2::box>
@endsection
