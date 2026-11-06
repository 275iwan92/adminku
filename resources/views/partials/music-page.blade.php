{{-- REMARK
 Tujuan       : Halaman Musik Latar di panel Profil (dekat Setup): hidup/mati, volume, pilihan trek. Dikendalikan skrip partials.music.
 Dipakai oleh : home.blade.php (slot profilePages).
 Status       : Aktif.
--}}
    <section data-lm-page="music" data-label="Musik Latar" data-icon="fa-music">
        <div class="am-set">
            <label class="am-row"><input type="checkbox" id="am-on"> <span>Putar musik latar</span></label>
            <label class="am-row am-col"><span>Volume <b id="am-volv"></b></span><input type="range" id="am-vol" min="0" max="100" step="1" aria-label="Volume musik latar"></label>
            <label class="am-row am-col"><span>Trek</span><select id="am-track" aria-label="Pilihan trek"></select></label>
            <p class="am-note">Bawaan mati. Pengaturan tersimpan di perangkat ini. Musik berhenti sendiri saat tab disembunyikan. Judul dan pencipta lagu selalu tampil di pojok kanan bawah selama musik berputar.</p>
        </div>
    </section>
