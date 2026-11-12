{{-- REMARK
 Tujuan       : Bagian Musik dan Animasi layar di dalam dialog Setup (data-lm-in=setup, bersama Tema dan Bahasa dari DevLib): hidup/mati, volume, pilihan trek. Dikendalikan skrip partials.music.
 Dipakai oleh : home.blade.php (slot profilePages).
 Status       : Aktif.
--}}
    <section data-lm-page="music" data-lm-in="setup" data-label="Musik" data-icon="fa-music">
        <div class="am-set">
            <label class="am-row"><input type="checkbox" id="am-on"> <span>Putar musik latar</span></label>
            <label class="am-row am-col"><span>Volume <b id="am-volv"></b></span><input type="range" id="am-vol" min="0" max="100" step="1" aria-label="Volume musik latar"></label>
            <label class="am-row am-col"><span>Trek</span><select id="am-track" aria-label="Pilihan trek"></select></label>
            <p class="am-note">Bawaan mati. Pengaturan tersimpan di perangkat ini. Musik berhenti sendiri saat tab disembunyikan. Judul dan pencipta lagu selalu tampil di pojok kanan bawah selama musik berputar.</p>
        </div>
    </section>

    <section data-lm-page="gerak" data-lm-in="setup" data-label="Animasi layar" data-icon="fa-wand-magic-sparkles">
        <div class="am-set">
            <div role="radiogroup" aria-label="Animasi layar" id="gk-grp" style="display:flex;gap:6px;flex-wrap:wrap">
                <label class="gk-o"><input type="radio" name="gk" value="auto"><span>Ikuti perangkat</span></label>
                <label class="gk-o"><input type="radio" name="gk" value="baru"><span>Nyala</span></label>
                <label class="gk-o"><input type="radio" name="gk" value="lama"><span>Kurangi gerak</span></label>
            </div>
            <p class="am-note">Memengaruhi hover, kemiringan kartu, dan hero Beranda. "Ikuti perangkat" mengikuti pengaturan kurangi gerakan di sistem.</p>
        </div>
    </section>
    <style>
        .gk-o { cursor: pointer; }
        .gk-o input { position: absolute; opacity: 0; }
        .gk-o span { display: block; padding: 7px 12px; border: 1px solid var(--dl2-border, #ccc); border-radius: 8px; font-size: 13px; }
        .gk-o input:checked + span { background: var(--dl2-primary, #2f7d4f); color: #fff; border-color: transparent; }
        .gk-o input:focus-visible + span { outline: 2px solid var(--dl2-primary, #2f7d4f); outline-offset: 2px; }
    </style>
    <script>
        addEventListener('DOMContentLoaded', () => {
            const g = window.sstockGaya, rs = document.querySelectorAll('#gk-grp input'); if (!g) return;
            const saved = () => { try { return localStorage.getItem('adminku.gaya'); } catch (e) { return null; } };
            const show = () => { const v = ['baru', 'lama'].includes(saved()) ? saved() : 'auto'; rs.forEach(r => r.checked = r.value === v); };
            rs.forEach(r => r.addEventListener('change', () => {
                if (r.value === 'auto') { try { localStorage.removeItem('adminku.gaya'); } catch (e) {} g.set(g.reduced ? 'lama' : 'baru', false); } else g.set(r.value, true);
            }));
            window.addEventListener('sstock-gaya', show); show();
        });
    </script>
