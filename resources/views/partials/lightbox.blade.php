{{-- REMARK
 Tujuan       : Pratinjau gambar pop-up (lightbox) di tengah layar untuk unggahan berkas bergambar (Foto Profil, Scan KTP, Foto KTP): latar gelap, gambar terpusat dan muat layar, tutup (X, Esc, klik di luar), zoom +/- (tombol atau roda mouse), geser saat diperbesar, cubit untuk zoom di layar sentuh, dan unduh.
                DevLib belum punya komponen lightbox (x-dl::image-gallery hanya membuka tab baru), jadi dibuat sederhana di sini. Berkas bukan gambar (PDF) tetap dibuka di tab baru.
 Sumber hulu  : markup x-dl::field.upload (gambar kecil .dl-upload__thumb di dalam tautan berkas tersimpan atau baris berkas yang baru dipilih).
 Input        : tanpa prop.
 Output       : CSS dan skrip; dipasang sekali per halaman.
 Dipakai oleh : employees.form, candidates.form.
 Status       : Aktif.
--}}
<style>
    .dl-upload__thumb { cursor: zoom-in; }
    .rk-photos { display: flex; flex-wrap: wrap; gap: 24px; margin: 4px 0 12px; }
    .rk-photo-cell { display: flex; flex-direction: column; gap: 4px; max-width: 320px; }
    .rk-photo-cell--ktp { max-width: 340px; }
    .rk-photo-label { font-size: 12.5px; font-weight: 600; }
    .rk-photo-hint { font-size: 11.5px; color: var(--dl-muted, #666); }
    .rk-photo-err { font-size: 11.5px; color: var(--dl-danger, #c0392b); }
    .rk-zoom { font-size: 12px; text-decoration: none; }
    .lb-ov { position: fixed; inset: 0; z-index: 10000; background: rgba(10, 12, 18, .88); display: flex; align-items: center; justify-content: center; overflow: hidden; touch-action: none; }
    .lb-img { max-width: 92vw; max-height: 86vh; transform-origin: center; user-select: none; -webkit-user-drag: none; background: #fff; border-radius: 6px; cursor: grab; }
    .lb-bar { position: absolute; top: 12px; right: 12px; display: flex; gap: 6px; }
    .lb-bar button, .lb-bar a { min-width: 38px; height: 38px; display: inline-flex; align-items: center; justify-content: center; border: none; border-radius: 8px; background: rgba(255, 255, 255, .16); color: #fff; font-size: 18px; cursor: pointer; text-decoration: none; }
    .lb-bar button:hover, .lb-bar a:hover { background: rgba(255, 255, 255, .3); }
    .lb-cap { position: absolute; bottom: 12px; left: 0; right: 0; text-align: center; color: #e8e8e8; font-size: 12.5px; }
</style>
<script>
    (() => {
        const open = (src, name) => {
            const ov = document.createElement('div');
            ov.className = 'lb-ov';
            ov.setAttribute('role', 'dialog'); ov.setAttribute('aria-modal', 'true'); ov.setAttribute('aria-label', name || 'Pratinjau gambar');
            ov.innerHTML = `<img class="lb-img" alt=""><div class="lb-bar"><button type="button" data-a="in" title="Perbesar" aria-label="Perbesar">+</button><button type="button" data-a="out" title="Perkecil" aria-label="Perkecil">&minus;</button><button type="button" data-a="fit" title="Ukuran pas" aria-label="Ukuran pas">1:1</button><a data-a="dl" title="Unduh" aria-label="Unduh" download>&#8681;</a><button type="button" data-a="x" title="Tutup (Esc)" aria-label="Tutup">&times;</button></div><div class="lb-cap"></div>`;
            const img = ov.querySelector('.lb-img');
            img.src = src;
            ov.querySelector('[data-a="dl"]').href = src;
            ov.querySelector('[data-a="dl"]').setAttribute('download', name || 'gambar');
            ov.querySelector('.lb-cap').textContent = (name || '') + '  ·  roda mouse atau tombol +/- untuk zoom, seret untuk menggeser';
            let k = 1, x = 0, y = 0;
            const draw = () => { img.style.transform = `translate(${x}px, ${y}px) scale(${k})`; };
            const zoom = (f) => { k = Math.min(8, Math.max(.5, k * f)); if (k <= 1) { x = y = 0; } draw(); };
            const close = () => { ov.remove(); document.removeEventListener('keydown', onKey, true); };
            const onKey = (e) => { if (e.key === 'Escape') { e.stopPropagation(); close(); } else if (e.key === '+' || e.key === '=') { zoom(1.25); } else if (e.key === '-') { zoom(.8); } };
            document.addEventListener('keydown', onKey, true);
            ov.addEventListener('click', (e) => {
                const a = e.target.closest('[data-a]')?.dataset.a;
                if (a === 'in') { zoom(1.25); } else if (a === 'out') { zoom(.8); } else if (a === 'fit') { k = 1; x = y = 0; draw(); } else if (a === 'x' || e.target === ov) { close(); }
            });
            ov.addEventListener('wheel', (e) => { e.preventDefault(); zoom(e.deltaY < 0 ? 1.15 : 1 / 1.15); }, { passive: false });
            // Geser (satu jari / mouse) dan cubit (dua jari).
            const pts = new Map(); let base = 0, k0 = 1;
            const dist = () => { const [a, b] = [...pts.values()]; return Math.hypot(a.x - b.x, a.y - b.y); };
            img.addEventListener('pointerdown', (e) => { img.setPointerCapture(e.pointerId); pts.set(e.pointerId, { x: e.clientX, y: e.clientY }); if (pts.size === 2) { base = dist(); k0 = k; } });
            img.addEventListener('pointermove', (e) => {
                if (! pts.has(e.pointerId)) { return; }
                const p = pts.get(e.pointerId);
                if (pts.size === 2) { pts.set(e.pointerId, { x: e.clientX, y: e.clientY }); k = Math.min(8, Math.max(.5, k0 * dist() / base)); draw(); return; }
                if (k > 1) { x += e.clientX - p.x; y += e.clientY - p.y; draw(); }
                pts.set(e.pointerId, { x: e.clientX, y: e.clientY });
            });
            const up = (e) => { pts.delete(e.pointerId); };
            img.addEventListener('pointerup', up); img.addEventListener('pointercancel', up);
            document.body.appendChild(ov);
        };

        // Tautan "Perbesar" di bawah kotak foto membuka pratinjau yang sama.
        document.addEventListener('click', (e) => {
            const a = e.target.closest('[data-lightbox]');
            if (! a) { return; }
            e.preventDefault();
            open(a.dataset.lightbox, a.dataset.name || '');
        });

        // Dialog crop DevLib (x-dl::photo-box) bertitle "Profile photo" dan berteks 3:4 bawaan; judul disesuaikan per kotak, teks KTP (856 x 540) juga.
        setTimeout(() => document.querySelectorAll('[data-dl-photobox]').forEach((box) => {
            const layer = window.Devlib?.h?.layer?.(box.dataset.cropId) || document.getElementById(box.dataset.cropId);
            if (! layer) { return; }
            const ktp = box.dataset.outW === '856';
            const title = [...layer.querySelectorAll('*')].find((el) => el.children.length === 0 && el.textContent.trim() === 'Profile photo');
            if (title) { title.textContent = ktp ? 'Scan KTP' : 'Foto profil'; }
            const hint = layer.querySelector('.dl-photobox__hint');
            if (hint && ktp) { hint.textContent = 'Geser gambar KTP di dalam bingkai dan atur zoom. Hasilnya 856 x 540 px, seukuran kartu (rasio 1,586 : 1).'; }
        }), 600);

        // Klik gambar kecil: unggahan tersimpan (tautan ke berkas) atau berkas yang baru dipilih (gambar sementara). PDF tidak punya gambar kecil, jadi tetap tab baru.
        document.addEventListener('click', (e) => {
            const thumb = e.target.closest('.dl-upload__thumb');
            if (! thumb) { return; }
            e.preventDefault(); e.stopPropagation();
            const link = thumb.closest('a');
            open(link?.href || thumb.src, thumb.closest('.dl-upload__file')?.querySelector('.dl-upload__name')?.textContent?.trim() || '');
        }, true);
    })();
</script>
