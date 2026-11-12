{{-- REMARK
 Tujuan       : Halaman login Simple Stock (sstock), memakai x-dl2::frame + x-dl2::login-box dari DevLib.
 Sumber hulu  : DevLib (Frame, LoginBox), gambar latar public/media/bg/sstock, kutipan public/media/text/motivasi.txt (salinan DevLib).
 Input        : error (pesan login gagal), username (isian terakhir) dari AuthController::show.
 Output       : form login yang diposting ke route login.attempt.
 Dipakai oleh : route login.
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Sign in', 'bodyClass' => 'sstock-bare'])
@section('body')
    <x-dl2::frame bg="sstock" bg-order="random" box="right" color="light" widgets="brand@top-left, clock@top-right, quote@bottom-left" quotes="motivasi.txt">
        @if (session('status'))<x-dl2::alert tone="success" :message="session('status')" />@endif
        <x-dl2::login-box :config="['token' => csrf_token(), 'usernameName' => 'login']" show="brand, title, username, password, forgot, button" :forgot-url="route('password.request')" :action="route('login.attempt')" :error="$error ?? ($errors->first() ?: null)" :username="$username" :brand="config('devlib.brand.name')" :tagline="config('devlib.brand.tagline')" />
        <x-dl2::env-badge part="version" variant="loginver" />
        @if (config('sstock.demo'))<p class="adminku-demo-note">Data demo, bisa direset</p>@endif
    </x-dl2::frame>
    {{-- Layar sempit (< 600 px) tanpa pilihan tampilan: langsung Tampilan HP (auto); popup tetap sebagai cadangan; di layar lebar tidak pernah terlihat. --}}
    <x-dl2::phone-offer :action="route('ui.set')" auto />
    @include('partials.csrf-keepalive')
@endsection
