{{-- REMARK
 Tujuan       : Layar utama AdminKU (Beranda global): DevLib 2 Layer Shell; Beranda = tab pertama (iframe ke home.board: banner sapaan + satu kartu ringkas per modul); tiap menu terbuka sebagai tab (iframe).
 Sumber hulu  : HomeController (modules menurut peran, open, fresh), HomeController::board (hero, cards dari HomeCards::page).
 Input        : modules, user, open, fresh.
 Output       : x-dl2::layer-shell; Musik Latar dan Animasi layar = dialog yang dibuka dari menu profil (link key=music).
 Dipakai oleh : route home.
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Home', 'bodyClass' => 'sstock-bare'])
@section('body')
    @php $net = app('devlib.net'); $ver = app('devlib.version'); @endphp   {{-- baris jaringan (IP client -> server + ping tanpa sesi) dan label versi di kaki panel kiri --}}
    <x-dl2::layer-shell :without="['chat', 'ai']" :help-url="route('help.user-guide')" :devices-url="route('devices.index')" :modules="$modules" :user="$user" :logout-action="route('logout')" :open="$open" :fresh="$fresh" menu-source="App\Support\Menu"
        :links="[['key' => 'music', 'label' => 'Musik dan Animasi', 'icon' => 'fa-solid fa-music', 'url' => '#music']]"
        :net-line="['clientIp' => $net['client_ip'], 'clientHost' => $net['client_host'], 'serverIp' => $net['server_ip'], 'serverHost' => $net['server_host'], 'pingUrl' => route('devlib.ping'), 'labels' => ['offline' => \Devlib2\Lang::t('net_offline')]]" :version="trim($ver['text'].' '.$ver['letter'])"
        :config="['home' => ['id' => 'home', 'title' => 'Beranda', 'href' => route('home.board')], 'csrf' => csrf_token()]" id="adminku-shell" style="height:100vh"></x-dl2::layer-shell>
    {{-- Musik latar + animasi layar: dialog (isi partials.music-page) dibuka dari menu profil. --}}
    <div id="am-dialog" hidden style="position:fixed;inset:0;z-index:80;background:rgba(0,0,0,.45);display:none;align-items:center;justify-content:center">
        <div style="background:var(--dl2-surface);color:var(--dl2-ink);border-radius:12px;padding:16px 18px;min-width:300px;max-width:92vw">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px"><b>Musik dan Animasi</b><x-dl2::button variant="light" size="sm" label="Tutup" id="am-close" /></div>
            @include('partials.music-page')
        </div>
    </div>
    @include('partials.music')
    <x-dl2::phone-offer :config="['action' => route('ui.set'), 'auto' => true]" />
    <script>
        (() => {
            const dlg = document.getElementById('am-dialog');
            const show = on => { dlg.hidden = !on; dlg.style.display = on ? 'flex' : 'none'; };
            document.getElementById('am-close').addEventListener('click', () => show(false));
            dlg.addEventListener('click', e => { if (e.target === dlg) show(false); });
            document.addEventListener('keydown', e => { if (e.key === 'Escape') show(false); });
            document.addEventListener('account.dl2.layer-shell', e => { if (e.detail && e.detail.key === 'music') show(true); });
            // Kartu modul di Beranda (iframe) meminta induk membuka menu yang sama sebagai tab kerja.
            window.addEventListener('message', e => {
                if (e.origin !== location.origin || !e.data || !['open-tab', 'open-item'].includes(e.data.dl)) return;
                const el = document.getElementById('adminku-shell'), inst = window.Devlib2 && Devlib2.classes['layer-shell'] && Devlib2.classes['layer-shell'].getInstance(el);
                if (!inst) return;
                if (e.data.dl === 'open-item') inst.open({id: String(e.data.code), title: e.data.label, href: e.data.url});
                else inst.open({id: String(e.data.code)});
            });
        })();
    </script>
@endsection
