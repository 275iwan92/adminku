{{-- REMARK
 Tujuan       : Isi Beranda (tab pertama Layer Shell, dibuka sebagai iframe): banner sapaan, kartu ringkas per modul, catatan selamat datang.
 Sumber hulu  : HomeController::board (HomeCards::page: hero, cards).
 Input        : hero, cards.
 Output       : halaman polos; klik kartu ber-data-open-tab mengirim pesan open-tab ke Layer Shell induk.
 Dipakai oleh : route home.board (href tab Beranda di home.blade.php).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Home', 'bodyClass' => 'sstock-bare'])
@section('body')
    @include('partials.flash')
    @include('partials.home-hero', ['hero' => $hero])
    @include('partials.home-cards', ['cards' => $cards, 'phone' => false])
    @include('partials.home-welcome')
    <script>
        const openTab = el => (window.parent !== window ? window.parent : window).postMessage({dl: 'open-tab', code: el.dataset.openTab}, location.origin);
        document.addEventListener('click', e => { const el = e.target.closest('[data-open-tab]'); if (el) { e.preventDefault(); openTab(el); } });
        document.addEventListener('keydown', e => { const el = e.target.closest?.('[data-open-tab]'); if (el && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); openTab(el); } });
    </script>
@endsection
