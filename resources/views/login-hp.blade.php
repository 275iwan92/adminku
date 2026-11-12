{{-- REMARK
 Tujuan       : Halaman login tampilan HP (smartphone potret): memakai x-dl2::phone-login dari DevLib (satu kolom mengalir 320-440 px, isian besar, tautan Tampilan Desktop hanya untuk jendela komputer yang dipersempit; ponsel asli selalu Tampilan HP).
 Sumber hulu  : AuthController::show (error, username); DevLib x-dl2::phone-login, Devlib2\Device.
 Input        : error (pesan login gagal), username (isian terakhir).
 Output       : form login yang diposting ke route login.attempt.
 Dipakai oleh : route login bila perangkat = hp (ponsel atau pilihan pengguna). Latar: x-hp-wallpaper (foto tegak).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Sign in', 'bodyClass' => 'sstock-bare sstock-hp'])
@section('body')
    <x-hp-wallpaper>
        <x-dl2::phone-login :config="['token' => csrf_token(), 'usernameName' => 'login']" :action="route('login.attempt')" :error="$error ?? ($errors->first() ?: null)" :username="$username" :forgot-url="route('password.request')" :brand="config('devlib.brand.name')" :tagline="config('devlib.brand.tagline')" :device-action="\App\Support\HpShell::deviceAction()">
            @if (session('status'))<x-dl2::alert tone="success" :message="session('status')" />@endif
            <x-dl2::env-badge part="version" variant="loginver" :label="trim(app('devlib.version')['text'].' '.app('devlib.version')['letter'])" />
            @if (config('sstock.demo'))<p class="adminku-demo-note">Data demo, bisa direset</p>@endif
        </x-dl2::phone-login>
    </x-hp-wallpaper>
    @include('partials.csrf-keepalive')
@endsection
