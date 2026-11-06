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
    <x-dl::layer-shell :classic="false" :without="['chat', 'ai']" :help-url="route('help.user-guide')" :devices-url="route('devices.index')" :modules="$modules" :user="$user" :logout-action="route('logout')" :open="$open" :fresh="$fresh" menu-source="App\Support\Menu">
        <x-slot:dashboard>
            @include('partials.flash')
            @include('partials.home-hero', ['hero' => $hero])
            @include('partials.home-cards', ['cards' => $cards, 'phone' => false])
        </x-slot:dashboard>
        {{-- Musik latar: halaman Musik Latar di panel Profil (bawaan mati); pil kredit + pemutar di partials.music. --}}
        <x-slot:profilePages>@include('partials.music-page')</x-slot:profilePages>
    </x-dl::layer-shell>
    @include('partials.music')
    {{-- Jendela / layar sempit tanpa pilihan tampilan (mis. login lewat tab lama atau mode Responsive DevTools): pindah ke Tampilan HP di Beranda juga (auto); hanya Beranda, tab-tab di dalamnya adalah iframe. --}}
    <x-dl::phone-offer :action="route('ui.set')" auto />
    {{-- Kartu modul ber-data-open-tab membuka menu yang sama sebagai tab kerja Layer Shell (pesan open-tab ke Shell). --}}
    <script>
        const openTab = el => window.postMessage({dl: 'open-tab', code: el.dataset.openTab}, location.origin);
        document.addEventListener('click', e => { const el = e.target.closest('[data-open-tab]'); if (el) { e.preventDefault(); openTab(el); } });
        document.addEventListener('keydown', e => { const el = e.target.closest?.('[data-open-tab]'); if (el && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); openTab(el); } });
    </script>
    {{-- Induk di akar menu (Data Master, Stok, GA, HR, Purchase): mengkliknya juga membuka layar pembukanya sebagai tab. DevLib hanya punya halaman pembuka untuk subgrup (Transaksi, Laporan), jadi induk akar ditangani di sini dari kunci open pada $modules. --}}
    <script>
        (() => {
            const shell = document.querySelector('[data-dl-modules]');
            const opens = Object.fromEntries(JSON.parse(shell.dataset.dlModules).filter(m => m.open).map(m => [m.label, m]));
            document.addEventListener('click', e => {
                const a = e.target.closest('.dl-lm__a[data-lm-x]'), pane = a?.closest('.dl-lm__pane'), m = a && opens[a.title];
                // Tangkap dulu (Layer Menu menggambar ulang panel saat klik), buka tab sesudah Layer Menu selesai turun satu layer.
                if (m && pane === pane.parentElement.firstElementChild) setTimeout(() => shell.dlComponent.openUrlTab('H-' + m.code, 'Beranda ' + m.label, m.open, m.icon));
            }, true);
            // Judul induk di atas daftar butir ("Stok" dst.) juga bisa diklik: membuka layar pembuka lagi (mis. setelah tabnya ditutup); layer tidak berubah.
            document.addEventListener('click', e => {
                const t = e.target.closest('.dl-lm__title'), m = t && opens[t.title];
                if (m) shell.dlComponent.openUrlTab('H-' + m.code, 'Beranda ' + m.label, m.open, m.icon);
            });
        })();
    </script>
@endsection
