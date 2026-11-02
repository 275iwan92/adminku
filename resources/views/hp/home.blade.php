{{-- REMARK
 Tujuan       : Beranda tampilan HP (global): banner hero (partial home-hero) dan satu kartu ringkas per modul yang boleh dilihat pengguna, menumpuk ke bawah. Isi Stok (ubin, pengingat opname, Cari Barang) ada di Beranda Stok HP (hp.stock-home).
 Sumber hulu  : HpController@home (hero, cards dari HomeCards::page).
 Input        : hero, cards.
 Output       : banner + partial home-cards (tautan ke tampilan HP tiap modul).
 Dipakai oleh : HomeController bila perangkat = hp.
 Status       : Aktif.
--}}
@extends('hp.layout')
@section('content')
    @include('partials.flash')
    @include('partials.home-hero', ['hero' => $hero])
    @include('partials.home-cards', ['cards' => $cards, 'phone' => true])
@endsection
