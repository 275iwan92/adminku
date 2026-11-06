{{-- REMARK
 Tujuan       : Daftar master Aset (modul FAA): kartu per kategori aset yang bisa dibuka (gaya Lokasi); tiap kartu aset memuat kode, gedung/ruang, dan lencana kondisi.
 Sumber hulu  : FacAssetController@index (groups).
 Input        : groups.
 Output       : partial fac-cardlist; klik kartu membuka form Aset.
 Dipakai oleh : route fac-assets.index (tombol Aset di panel Perawatan Gedung dan Aset).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Aset'])
@section('body')
    @include('partials.flash')
    @include('partials.back-to-hub', ['hub' => 'fac'])
    @include('partials.fac-cardlist', ['groups' => $groups, 'newUrl' => route('fac-assets.create'), 'searchHint' => 'Cari kode, nama, lokasi aset...'])
@endsection
