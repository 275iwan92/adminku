{{-- REMARK
 Tujuan       : Layar atur password baru dari tautan e-mail (token + e-mail di tautan).
 Sumber hulu  : DevLib (Frame, Brand, field, Button); PasswordResetController@reset.
 Input        : token, email, errors.
 Output       : POST password.update.
 Dipakai oleh : route password.reset (tautan di e-mail reset).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Reset password', 'bodyClass' => 'sstock-bare'])
@section('body')
    @if (\Devlib2\Device::view(request()) === \Devlib2\Device::PHONE)
        <x-hp-wallpaper><main class="ak-plogin">@include('auth._reset-password-box')</main></x-hp-wallpaper>
    @else
        <x-dl2::frame bg="sstock" bg-order="random" box="right" color="light" widgets="brand@top-left, clock@top-right, quote@bottom-left" quotes="motivasi.txt" :brand="config('devlib.brand.name')" :tagline="config('devlib.brand.tagline')" :logo="config('devlib.brand.logo')">
            @include('auth._reset-password-box')
        </x-dl2::frame>
    @endif
@endsection
