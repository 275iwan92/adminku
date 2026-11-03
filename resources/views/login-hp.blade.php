{{-- REMARK
 Tujuan       : Halaman login tampilan HP (smartphone potret): memakai x-dl::phone-login dari DevLib (satu kolom mengalir 320-440 px, isian besar, tautan Tampilan Desktop hanya untuk jendela komputer yang dipersempit; ponsel asli selalu Tampilan HP).
 Sumber hulu  : AuthController::show (error, username); DevLib x-dl::phone-login, Devlib\Device.
 Input        : error (pesan login gagal), username (isian terakhir).
 Output       : form login yang diposting ke route login.attempt.
 Dipakai oleh : route login bila perangkat = hp (ponsel atau pilihan pengguna). Latar: x-hp-wallpaper (foto tegak).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Sign in', 'bodyClass' => 'sstock-bare sstock-hp'])
@section('body')
    <x-hp-wallpaper>
        <x-dl::phone-login :action="route('login.attempt')" :error="$error ?? ($errors->first() ?: null)" :username="$username" :forgot-url="route('password.request')" :brand="config('devlib.brand.name')" :tagline="config('devlib.brand.tagline')" :device-action="\App\Support\HpShell::deviceAction()">
            @if (session('status'))<x-dl::alert tone="success" :message="session('status')" />@endif
            <x-dl::env-badge part="version" variant="loginver" />
        </x-dl::phone-login>
    </x-hp-wallpaper>
    @include('partials.csrf-keepalive')
@endsection
