{{-- REMARK
 Tujuan       : Layar pembuka kelompok menu / modul tampilan HP (menggantikan daftar kelompok): ubin menumpuk, tautan ke tampilan HP tiap layar.
 Sumber hulu  : OpeningController (page dari App\Support\Opening::build).
 Input        : page.
 Output       : partial opening-body (phone).
 Dipakai oleh : route opening bila perangkat = hp.
 Status       : Aktif.
--}}
@extends('hp.layout')
@section('content')
    @include('partials.opening-body', ['page' => $page, 'phone' => true])
@endsection
