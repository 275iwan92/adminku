{{-- REMARK
 Tujuan       : Halaman cetak Stok Opname (A4 landscape, x-dl2::print-sheet): LEMBAR HITUNG (kind=sheet) atau BERITA ACARA (kind=report; pratinjau saat Tinjau).
 Sumber hulu  : StockCountController@sheet / report (data = App\Support\StockCountPrint).
 Input        : count, data (prop print-sheet), kind.
 Output       : x-dl2::action-bar (Kembali + Print / PDF), x-dl2::print-sheet.
 Dipakai oleh : routes stock-counts.sheet, stock-counts.report; tombol di stock-counts/header.
 Status       : Aktif.
--}}
@extends('layout', ['title' => $data['title']])
@section('body')
    <x-dl2::box>
        <x-dl2::action-bar>
            <x-dl2::button variant="secondary" icon="back" label="Back" :href="route('stock-counts.show', $count)" />
            <x-dl2::doc-share target="#print-sheet" :type="'print-stock-count-'.$kind" :doc-id="$count->id" :number="$count->doc_no" :title="$data['title']" :email="false" orientation="landscape" />
        </x-dl2::action-bar>
        <x-dl2::print-sheet id="print-sheet" :title="$data['title']" :code="$data['code']" :info="$data['info']" :sections="$data['sections']" :signatures="$data['signatures']" :note="$data['note']" :landscape="true" />
    </x-dl2::box>
@endsection
