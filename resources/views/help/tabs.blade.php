{{-- REMARK
 Tujuan       : Tab di atas halaman HELP: Panduan Pengguna (UG, semua yang login) dan Technical Guide (TG, tautannya HANYA dirender untuk Administrator; rutenya tetap dijaga middleware administrator).
 Input        : active ('ug' | 'tg').
 Dipakai oleh : help/user-guide.blade.php, help/technical-guide.blade.php.
 Status       : Aktif.
--}}
<nav class="g-tabs" aria-label="Help">
    <a href="{{ route('help.user-guide') }}" @class(['is-on' => $active === 'ug'])>Panduan Pengguna</a>
    @if (auth()->user()->isAdministrator())
        <a href="{{ route('help.technical-guide') }}" @class(['is-on' => $active === 'tg'])>Technical Guide</a>
    @endif
    <a class="g-close" href="{{ route('home') }}" target="_top" aria-label="Tutup panduan dan kembali ke Beranda" title="Tutup dan kembali ke Beranda"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg><span>Tutup</span></a>
</nav>
