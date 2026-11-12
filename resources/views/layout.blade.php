{{-- REMARK
 Tujuan       : Kerangka HTML semua halaman SStock (Simple Stock): aset DevLib + tema + Tailwind.
 Sumber hulu  : ../devlib (devlib.css, devlib.js, x-dl::theme-boot), ../devlib2 (@devlib2, koeksistensi), resources/css/app.css.
 Input        : title, bodyClass dari view anak.
 Dipakai oleh : @extends oleh semua view SStock.
 Status       : Aktif.
--}}
<!DOCTYPE html>
<html lang="{{ \Devlib\Locale::lang() }}" data-dl-tz="{{ config('devlib.timezone') }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="{{ \Devlib\Device::viewport(request()) }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ \Devlib\Lang::text($title ?? config('app.name')) }} — {{ config('app.name') }}{{ config('sstock.demo') ? ' (DEMO)' : '' }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="{{ \Devlib\DevlibServiceProvider::asset('devlib.css') }}">
    <x-dl::theme-boot />
    {{-- DevLib 2 berdampingan dengan v1 (komponen daun x-dl2::*); tema v1 (dl-theme) diterjemahkan ke terang/gelap v2. --}}
    @devlib2
    <script>(function(d){var b=function(){var t=d.dataset.dlTheme||"";d.dataset.dl2Theme=/^(dark|hitam)/.test(t)?"dark":"light"};b();document.addEventListener("DOMContentLoaded",b)})(document.documentElement);</script>
    <script src="{{ \Devlib\DevlibServiceProvider::asset('devlib.js') }}" defer></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="dl-body {{ $bodyClass ?? '' }} {{ \Devlib\Device::bodyClass(request()) }}">
    @yield('body')
    @if (config('sstock.demo'))<div class="adminku-demo-badge" title="Data demo, bisa direset" aria-label="Versi demo">DEMO</div>@endif
    {{-- Logout otomatis: hanya halaman berlogin (tamu tidak punya sesi); lamanya = umur sesi server, ping memperpanjang sesi. --}}
    @auth
        <x-dl::idle-logout :minutes="config('session.lifetime')" logout-url="{{ route('logout') }}" ping-url="{{ route('session.ping') }}" login-url="{{ route('login') }}" />
    @endauth
    <script>
        // Cegah kirim ganda (klik Simpan dua kali): form yang baru saja terkirim menolak kiriman kedua selama 8 detik.
        // Fase tangkap = menolak; fase gelembung = menandai hanya bila validasi form tidak membatalkan kiriman.
        document.addEventListener('submit', e => { if (e.target.dataset.sentAt && Date.now() - e.target.dataset.sentAt < 8000) { e.preventDefault(); e.stopImmediatePropagation(); } }, true);
        document.addEventListener('submit', e => { if (!e.defaultPrevented) e.target.dataset.sentAt = Date.now(); });
    </script>
</body>
</html>
