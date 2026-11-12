{{-- REMARK
 Tujuan       : Kerangka HTML semua halaman AdminKU: aset DevLib 2 + tema.
 Sumber hulu  : ../devlib2 (@devlib2, x-dl2::theme-boot), resources/css/app.css.
 Input        : title, bodyClass dari view anak.
 Dipakai oleh : @extends oleh semua view SStock.
 Status       : Aktif.
--}}
<!DOCTYPE html>
<html lang="{{ \Devlib2\Locale::lang() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="{{ \Devlib2\Device::viewport(request()) }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ \Devlib2\Lang::text($title ?? config('app.name')) }} — {{ config('app.name') }}{{ config('sstock.demo') ? ' (DEMO)' : '' }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <x-dl2::theme-boot />
    @devlib2
    <script>
        // Skrip halaman yang membaca isian DevLib 2 (dibangun JS saat DOMContentLoaded) dijalankan lewat akReady: sesudah komponen terpasang.
        window.akReady = fn => document.readyState === 'complete' ? setTimeout(fn) : window.addEventListener('load', () => setTimeout(fn));
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="dl2-body {{ $bodyClass ?? '' }} {{ \Devlib2\Device::bodyClass(request()) }}">
    <script>
        if (window.parent !== window) document.body.classList.add('ak-framed');
        // Tautan ber-data-dl2-open-item di dalam tab (iframe): minta Layer Shell induk membuka layarnya sebagai tab kerja.
        document.addEventListener('click', e => {
            const a = e.target.closest('a[data-dl2-open-item]');
            if (!a || window.parent === window || e.defaultPrevented || e.ctrlKey || e.metaKey || e.shiftKey) return;
            e.preventDefault();
            window.parent.postMessage({dl: 'open-item', code: a.dataset.dl2OpenItem, label: a.dataset.label || a.textContent.trim(), url: a.href}, location.origin);
        });
    </script>
    @yield('body')
    @if (config('sstock.demo'))<div class="adminku-demo-badge" title="Data demo, bisa direset" aria-label="Versi demo">DEMO</div>@endif
    {{-- Logout otomatis: hanya halaman berlogin (tamu tidak punya sesi); lamanya = umur sesi server, ping memperpanjang sesi. --}}
    @auth
        <x-dl2::idle-logout :minutes="config('session.lifetime')" logout-url="{{ route('logout') }}" ping-url="{{ route('session.ping') }}" login-url="{{ route('login') }}" />
    @endauth
    <script>
        // Cegah kirim ganda (klik Simpan dua kali): form yang baru saja terkirim menolak kiriman kedua selama 8 detik.
        // Fase tangkap = menolak; fase gelembung = menandai hanya bila validasi form tidak membatalkan kiriman.
        document.addEventListener('submit', e => { if (e.target.dataset.sentAt && Date.now() - e.target.dataset.sentAt < 8000) { e.preventDefault(); e.stopImmediatePropagation(); } }, true);
        document.addEventListener('submit', e => { if (!e.defaultPrevented) e.target.dataset.sentAt = Date.now(); });
    </script>
</body>
</html>
