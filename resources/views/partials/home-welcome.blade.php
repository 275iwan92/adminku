{{-- REMARK
 Tujuan       : Jendela sambutan untuk pendatang baru di Beranda (desktop dan HP): inti AdminKU dalam tiga butir dan tombol ke Panduan Pengguna (presentasi). Tampil setiap Beranda dibuka sampai pengguna mencentang "Jangan tampilkan lagi" (disimpan per pengguna per browser di localStorage); Panduan Pengguna tetap bisa dibuka dari tombol di banner.
 Input        : auth user (id untuk kunci penanda).
 Output       : <dialog class="sstock-welcome"> (CSS di resources/css/app.css).
 Dipakai oleh : home, hp.home.
 Status       : Aktif.
--}}
<dialog class="sstock-welcome" id="sstock-welcome" aria-labelledby="sstock-welcome-h">
    <button type="button" class="sstock-welcome__x" data-welcome-close aria-label="Tutup"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    <h2 id="sstock-welcome-h">Selamat datang di AdminKU</h2>
    <p>Baru pertama masuk? Intinya:</p>
    <ul>
        <li><i class="fa-solid fa-bars" aria-hidden="true"></i><span><b>Menu</b> ada di sebelah kiri (HP: tombol menu di pojok). Pilih modul, layarnya terbuka sebagai tab.</span></li>
        <li><i class="fa-solid fa-house" aria-hidden="true"></i><span><b>Beranda</b> ini merangkum yang perlu perhatian: stok, pengiriman, keuangan, kendaraan, AC.</span></li>
        <li><i class="fa-solid fa-book" aria-hidden="true"></i><span><b>Panduan Pengguna</b> menjelaskan alur kerja langkah demi langkah, bisa dibuka kapan saja dari tombol di banner.</span></li>
    </ul>
    <label class="sstock-welcome__off"><input type="checkbox" id="sstock-welcome-off"> Jangan tampilkan lagi (matikan panduan sambutan)</label>
    <div class="sstock-welcome__act">
        <a class="sstock-welcome__go" href="{{ route('help.user-guide') }}" data-welcome-close><i class="fa-solid fa-play" aria-hidden="true"></i> Lihat panduan</a>
        <button type="button" class="sstock-welcome__skip" data-welcome-close>Nanti saja</button>
    </div>
</dialog>
<script>
    (() => {
        const d = document.getElementById('sstock-welcome'), key = 'adminku.welcome.{{ auth()->id() }}';
        try { if (!d || localStorage.getItem(key)) return; } catch (e) { return; }
        const off = document.getElementById('sstock-welcome-off'), save = () => { if (off.checked) try { localStorage.setItem(key, '1'); } catch (e) {} };
        d.querySelectorAll('[data-welcome-close]').forEach(b => b.addEventListener('click', () => { save(); d.close(); }));
        d.addEventListener('cancel', save);   // Esc: centang tetap dihormati
        d.showModal();
    })();
</script>
