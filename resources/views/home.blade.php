{{-- REMARK
 Tujuan       : Layar utama AdminKU (Beranda global): Layer Shell + banner sapaan + satu kartu ringkas per modul yang boleh dilihat pengguna (Stok, GA Kendaraan, GA AC).
 Sumber hulu  : HomeController (modules menurut peran, hero, cards dari HomeCards::page).
 Input        : modules, user, open, fresh, hero, cards.
 Output       : x-dl::layer-shell; tiap menu terbuka sebagai tab (iframe) layar masing-masing; kartu membuka layar pembuka modulnya sebagai tab.
 Dipakai oleh : route home.
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Home', 'bodyClass' => 'sstock-bare'])
@section('body')
    {{-- Help & support (semua peran) membuka User's Guide; Technical Guide hanya tampil sebagai tab untuk Administrator. --}}
    <x-dl::layer-shell :classic="false" :without="['chat', 'ai', 'theme']" :help-url="route('help.user-guide')" :modules="$modules" :user="$user" :logout-action="route('logout')" :device-action="route('ui.set')" :open="$open" :fresh="$fresh" menu-source="App\Support\Menu">
        <x-slot:dashboard>
            @include('partials.flash')
            @include('partials.home-hero', ['hero' => $hero])
            @include('partials.home-cards', ['cards' => $cards, 'phone' => false])
        </x-slot:dashboard>
    </x-dl::layer-shell>
    {{-- Jendela / layar sempit tanpa pilihan tampilan (mis. login lewat tab lama atau mode Responsive DevTools): pindah ke Tampilan HP di Beranda juga (auto); hanya Beranda, tab-tab di dalamnya adalah iframe. --}}
    <x-dl::phone-offer :action="route('ui.set')" auto />
    {{-- Kartu modul ber-data-open-tab membuka menu yang sama sebagai tab kerja Layer Shell (pesan open-tab ke Shell). --}}
    <script>
        const openTab = el => window.postMessage({dl: 'open-tab', code: el.dataset.openTab}, location.origin);
        document.addEventListener('click', e => { const el = e.target.closest('[data-open-tab]'); if (el) { e.preventDefault(); openTab(el); } });
        document.addEventListener('keydown', e => { const el = e.target.closest?.('[data-open-tab]'); if (el && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); openTab(el); } });
    </script>
@endsection
