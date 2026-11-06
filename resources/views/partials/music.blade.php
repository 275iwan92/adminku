{{-- REMARK
 Tujuan       : Pemutar musik latar Beranda: pil kecil (bisukan + kredit judul/pencipta trek yang main), gaya, dan skrip pemutar.
 Input        : config('music.tracks'). Pengaturan di localStorage 'adminku-music' (per perangkat): on (bawaan MATI), mute, vol (0-100), track ('all' atau nama berkas), i, pos.
 Perilaku     : Beranda adalah shell dan tab kerja berupa iframe, jadi audio tidak terputus saat pindah modul. Setelah muat ulang/login posisi dilanjutkan; browser memblokir suara sebelum interaksi, jadi mulai pada klik/tombol pertama. Fade 1 detik, jeda saat tab tersembunyi.
 Dipakai oleh : home.blade.php. Belum ada di tampilan HP.
 Status       : Aktif.
--}}
@php($tracks = array_values(config('music.tracks', [])))
@if ($tracks)
    <style>
        .am-pill { position: fixed; right: 14px; bottom: 14px; z-index: 60; display: flex; align-items: center; gap: 8px; max-width: min(92vw, 420px); padding: 6px 12px 6px 6px; border-radius: 999px; font-size: 12px; line-height: 1.3; background: rgba(20, 24, 33, .78); color: #fff; backdrop-filter: blur(8px); box-shadow: 0 4px 16px rgba(0, 0, 0, .25); }
        .am-pill[hidden] { display: none; }
        .am-pill button { flex: none; width: 28px; height: 28px; border: 0; border-radius: 50%; cursor: pointer; background: rgba(255, 255, 255, .16); color: inherit; }
        .am-pill button:hover, .am-pill button:focus-visible { background: rgba(255, 255, 255, .3); outline: 2px solid #fff; outline-offset: 1px; }
        .am-pill a { color: #cfe3ff; text-decoration: underline; }
        .am-pill .am-credit { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .am-set { display: grid; gap: 14px; padding: 4px 2px; }
        .am-row { display: flex; gap: 8px; align-items: center; }
        .am-col { flex-direction: column; align-items: stretch; }
        .am-col select, .am-col input { width: 100%; }
        .am-note { font-size: 12px; opacity: .7; margin: 0; }
    </style>
    <div class="am-pill" id="am-pill" hidden>
        <button type="button" id="am-mute" aria-label="Bisukan musik" aria-pressed="false" title="Bisukan / suarakan musik"><i class="fa-solid fa-volume-high" aria-hidden="true"></i></button>
        <span class="am-credit" id="am-credit"></span>
    </div>
    <script>
        (() => {
            const T = @json($tracks), BASE = @json(asset('audio')) + '/', K = 'adminku-music';
            let s = { on: false, mute: false, vol: 25, track: 'all', i: 0, pos: 0 };
            try { Object.assign(s, JSON.parse(localStorage.getItem(K) || '{}')); } catch (e) {}
            const $ = id => document.getElementById(id), a = new Audio(), save = () => localStorage.setItem(K, JSON.stringify(s));
            const idx = () => s.track === 'all' ? (s.i % T.length) : Math.max(0, T.findIndex(t => t.file === s.track));
            const want = () => s.on && !s.mute, gain = () => Math.min(1, s.vol / 100);
            let fade, blocked = false, resumeAt = s.pos;

            const ramp = (to, done) => {
                clearInterval(fade);
                fade = setInterval(() => {
                    const d = to - a.volume;
                    if (Math.abs(d) < .02) { a.volume = to; clearInterval(fade); done && done(); } else a.volume = Math.min(1, Math.max(0, a.volume + Math.sign(d) * .025));
                }, 40);   // ±0,025 tiap 40 ms ≈ 1 detik penuh
            };
            const load = () => {
                const t = T[idx()];
                if (!a.src.endsWith(t.file)) { a.src = BASE + t.file; if (resumeAt) a.addEventListener('loadedmetadata', () => { a.currentTime = resumeAt; resumeAt = 0; }, { once: true }); }
                a.loop = s.track !== 'all';
                const c = $('am-credit'); c.textContent = '';
                const link = (txt, href) => { if (!href) return document.createTextNode(txt); const x = document.createElement('a'); x.href = href; x.target = '_blank'; x.rel = 'noopener'; x.textContent = txt; return x; };
                c.append('♪ ', t.title, ' — ', link(t.author, t.url), ' · ', link(t.license, t.license_url));
                c.title = c.textContent;
            };
            const draw = () => {
                $('am-pill').hidden = !s.on;
                const m = $('am-mute'); m.setAttribute('aria-pressed', s.mute); m.firstElementChild.className = 'fa-solid ' + (s.mute ? 'fa-volume-xmark' : 'fa-volume-high');
                const on = $('am-on'); if (on) { on.checked = s.on; $('am-vol').value = s.vol; $('am-volv').textContent = s.vol + '%'; $('am-track').value = s.track; }
            };
            const play = () => {
                if (!want() || document.hidden) return;
                load(); a.volume = 0;
                a.play().then(() => { blocked = false; ramp(gain()); }).catch(() => { blocked = true; });   // diblokir autoplay: tunggu interaksi pertama
            };
            const stop = () => ramp(0, () => a.pause());
            const apply = () => { save(); draw(); want() ? (a.paused ? play() : (load(), ramp(gain()))) : stop(); };

            a.addEventListener('ended', () => { if (s.track === 'all') { s.i = (idx() + 1) % T.length; s.pos = 0; save(); play(); } });
            document.addEventListener('visibilitychange', () => { if (document.hidden) a.pause(); else play(); });
            ['pointerdown', 'keydown'].forEach(ev => document.addEventListener(ev, () => { if (blocked) play(); }, true));
            setInterval(() => { if (!a.paused) { s.pos = Math.floor(a.currentTime); save(); } }, 2000);

            const sel = $('am-track');
            if (sel) {
                sel.append(new Option('Semua trek berurutan', 'all'), ...T.map(t => new Option(t.title + ' — ' + t.author, t.file)));
                document.addEventListener('change', e => {
                    if (e.target.id === 'am-on') { s.on = e.target.checked; apply(); }
                    if (e.target.id === 'am-track') { s.track = e.target.value; s.pos = 0; resumeAt = 0; a.removeAttribute('src'); apply(); }
                });
                document.addEventListener('input', e => { if (e.target.id === 'am-vol') { s.vol = +e.target.value; $('am-volv').textContent = s.vol + '%'; save(); if (!a.paused) a.volume = gain(); } });
            }
            $('am-mute').addEventListener('click', () => { s.mute = !s.mute; apply(); });
            // Tab lain mengubah pengaturan: ikuti (mis. dimatikan dari tab kerja lain).
            addEventListener('storage', e => { if (e.key === K) { try { Object.assign(s, JSON.parse(e.newValue || '{}')); } catch (x) {} draw(); want() ? play() : a.pause(); } });
            draw(); play();
        })();
    </script>
@endif
