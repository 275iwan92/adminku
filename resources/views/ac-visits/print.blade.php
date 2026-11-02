{{-- REMARK
 Tujuan       : Halaman cetak BERITA ACARA kunjungan perawatan AC (A4 potret, x-dl::print-sheet) dengan kotak tanda tangan vendor, PIC area, dan petugas GA; MODE (status pesanan) tercetak di kepala.
 Sumber hulu  : AcVisitController@print (data = App\Support\AcVisitPrint).
 Input        : order, data (prop print-sheet).
 Output       : x-dl::action-bar (Kembali + Print / PDF), x-dl::print-sheet.
 Dipakai oleh : route ac-visits.print; tombol Cetak Berita Acara di layar Kunjungan.
 Status       : Aktif.
--}}
@extends('layout', ['title' => $data['title']])
@section('body')
    <x-dl::box>
        <x-dl::action-bar>
            <x-dl::button class="sstock-btn-slate" variant="secondary" icon="back" label="Kembali" :href="route('ac-visits.show', $order)" />
            <x-dl::doc-share target="#print-sheet" type="print-ac-visit" :doc-id="$order->id" :number="$order->no" :title="$data['title']" :email="false" orientation="portrait" />
        </x-dl::action-bar>
        <x-dl::print-sheet id="print-sheet" :title="$data['title']" :code="$data['code']" :info="$data['info']" :sections="$data['sections']" :signatures="$data['signatures']" :note="$data['note']" :landscape="false" />
    </x-dl::box>
@endsection
