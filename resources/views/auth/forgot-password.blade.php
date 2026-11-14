{{-- REMARK
 Tujuan       : Layar lupa password: isi username atau e-mail, tautan reset dikirim ke e-mail akun.
 Sumber hulu  : DevLib (Frame, Brand, field, Button); PasswordResetController@request.
 Input        : session status (pesan sukses), errors.
 Output       : POST password.email.
 Dipakai oleh : route password.request (tautan "Lupa password?" di login).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Forgot password', 'bodyClass' => 'sstock-bare'])
@section('body')
    @if (\Devlib2\Device::view(request()) === \Devlib2\Device::PHONE)
        <x-hp-wallpaper><main class="ak-plogin">@include('auth._forgot-password-box')</main></x-hp-wallpaper>
    @else
        <x-dl2::frame :image-list="\App\Support\Wallpapers::list()" bg-order="random" box="right" color="light" widgets="brand@top-left, clock@top-right, quote@bottom-left" :quote-list="\App\Support\Wallpapers::quotes()" :brand="config('devlib.brand.name')" :tagline="config('devlib.brand.tagline')" :logo="config('devlib.brand.logo')">
            @include('auth._forgot-password-box')
        </x-dl2::frame>
    @endif
@endsection
