{{-- REMARK
 Tujuan       : Halaman cetak surat SP 1 / SP 2 / SP 3 / PHK (A4 potret, x-dl::print-sheet): kop perusahaan, judul, nomor, identitas karyawan, isi, tanda tangan penerbit dan karyawan.
 Sumber hulu  : DisciplineController@print (data = App\Support\DisciplinePrint).
 Input        : discipline, data (prop print-sheet).
 Output       : x-dl::action-bar (Kembali + Print / PDF), x-dl::print-sheet.
 Dipakai oleh : route disciplines.print; tombol Cetak di detail penerbitan.
 Status       : Aktif.
--}}
@extends('layout', ['title' => $data['title']])
@section('body')
    <x-dl::box>
        <x-dl::action-bar>
            <x-dl::button class="sstock-btn-slate" variant="secondary" icon="back" label="Kembali" :href="route('disciplines.show', $discipline)" />
            <x-dl::doc-share target="#print-sheet" type="print-discipline" :doc-id="$discipline->id" :number="$discipline->doc_no" :title="$data['title']" :email="false" orientation="portrait" />
        </x-dl::action-bar>
        <x-dl::print-sheet id="print-sheet" :title="$data['title']" :code="$data['code']" :info="$data['info']" :sections="$data['sections']" :signatures="$data['signatures']" :note="$data['note']" :landscape="false" />
    </x-dl::box>
@endsection
