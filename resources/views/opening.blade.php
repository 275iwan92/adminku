{{-- REMARK
 Tujuan       : Layar pembuka kelompok menu / modul (desktop), dibuka sebagai tab Layer Shell saat induk menu diklik.
 Sumber hulu  : OpeningController (page dari App\Support\Opening::build).
 Input        : page.
 Output       : partial opening-body.
 Dipakai oleh : route opening.
 Status       : Aktif.
--}}
@extends('layout', ['title' => $page['title']])
@section('body')
    @include('partials.opening-body', ['page' => $page, 'phone' => false])
@endsection
