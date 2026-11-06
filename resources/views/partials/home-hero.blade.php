{{-- REMARK
 Tujuan       : Banner "hero" Beranda desktop dan HP: sapaan menurut jam, keterangan peran / Grup / perusahaan, empat angka ringkasan, tombol Panduan Pengguna, jam hidup, tanggal panjang, dan kartu cuaca.
                Jam: skrip kecil memakai zona waktu aplikasi (data-tz, bukan zona perangkat) dan selisih jam server vs perangkat; tanpa JS yang tampil waktu server saat halaman dibuat.
                Cuaca: x-dl::weather (tempat cadangan = kota di layar Perusahaan); belum diisi = kartu tidak dirender (tidak ada cuaca palsu).
 Sumber hulu  : App\Support\HomeHero::build.
 Input        : hero (greeting, name, line, stats, time, date, tz, tzLabel, now, weather).
 Output       : banner dengan kelas sstock-hero* (CSS di resources/css/app.css).
 Dipakai oleh : home (desktop), hp.home.
 Status       : Aktif.
--}}
<div class="sstock-herobox"><section class="sstock-hero" data-sstock-hero data-tz="{{ $hero['tz'] }}" data-now="{{ $hero['now'] }}">
    <canvas class="sstock-hero__fx" aria-hidden="true"></canvas>
    <div class="sstock-hero__main">
        <div class="sstock-hero__kicker">AdminKU · Beranda</div>
        <h1 class="sstock-hero__hi"><span data-hero-greet>{{ $hero['greeting'] }}</span>, {{ $hero['name'] }}</h1>
        <p class="sstock-hero__meta">{{ implode(' · ', $hero['line']) }}</p>
        @if ($hero['stats'])
            <ul class="sstock-hero__stats">
                @foreach ($hero['stats'] as $s)
                    <li class="sstock-hero__stat"><b class="sstock-hero__num {{ $s['alert'] ? 'is-alert' : '' }}">{{ \Devlib\Locale::number($s['value'], 0) }}</b><span>{{ $s['label'] }}</span></li>
                @endforeach
            </ul>
        @endif
        <a class="sstock-hero__btn" href="{{ route('help.user-guide') }}"><i class="fa-solid fa-book" aria-hidden="true"></i> Panduan Pengguna</a>
    </div>
    <div class="sstock-hero__side">
        <div class="sstock-hero__clock"><span data-hero-time>{{ $hero['time'] }}</span> <small>{{ $hero['tzLabel'] }}</small></div>
        <div class="sstock-hero__date" data-hero-date>{{ $hero['date'] }}</div>
        @if ($hero['weather'])
            <x-dl::weather class="sstock-hero__wx" :city="$hero['weather']['city']" :lat="$hero['weather']['lat']" :lon="$hero['weather']['lon']" />
        @endif
    </div>
</section></div>
<script>
    (() => {
        const root = document.querySelector('[data-sstock-hero]');
        if (!root || !window.Intl) return;
        const tz = root.dataset.tz, skew = (+root.dataset.now || Date.now()) - Date.now();   // jam server dianggap benar
        const hm = new Intl.DateTimeFormat('en-GB', {timeZone: tz, hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23'});
        const hr = new Intl.DateTimeFormat('en-GB', {timeZone: tz, hour: 'numeric', hourCycle: 'h23'});
        const dt = new Intl.DateTimeFormat('id-ID', {timeZone: tz, weekday: 'long', day: 'numeric', month: 'long', year: 'numeric'});
        const t = root.querySelector('[data-hero-time]'), d = root.querySelector('[data-hero-date]'), g = root.querySelector('[data-hero-greet]');
        const tick = () => {
            const now = new Date(Date.now() + skew), h = +hr.format(now);
            t.textContent = hm.format(now);
            d.textContent = dt.format(now);
            g.textContent = 'Selamat ' + (h < 11 ? 'pagi' : h < 15 ? 'siang' : h < 19 ? 'sore' : 'malam');
        };
        tick();
        setInterval(tick, 1000);
    })();
</script>
{{-- Mode gerak Beranda (hero + kartu): 'baru' = beranimasi, 'lama' = tanpa animasi (kartu tetap bertampilan baru, statis). Urutan pilihan: ?gaya= di alamat, pilihan tersimpan (saklar, localStorage), lalu pengaturan sistem pengguna "kurangi gerakan" (prefers-reduced-motion) = tanpa animasi, selain itu beranimasi. Hasilnya di <html data-gaya>; saklar memanggil sstockGaya.set. --}}
<script>
    window.sstockGaya = (() => {
        const ok = g => g === 'baru' || g === 'lama', reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;
        const saved = () => { try { return localStorage.getItem('adminku.gaya'); } catch (e) { return null; } };
        const q = new URLSearchParams(location.search).get('gaya');
        const api = {
            reduced,
            get: () => document.documentElement.dataset.gaya,
            set(g, save) {
                document.documentElement.dataset.gaya = g;
                if (save) try { localStorage.setItem('adminku.gaya', g); } catch (e) {}
                window.dispatchEvent(new CustomEvent('sstock-gaya', {detail: g}));
            },
        };
        api.set(ok(q) ? q : ok(saved()) ? saved() : reduced ? 'lama' : 'baru', ok(q));
        return api;
    })();
</script>
{{-- Efek hero mode animasi: konstelasi titik di latar yang bereaksi pada kursor (canvas), angka ringkasan menghitung naik. Berhenti total di mode tanpa animasi atau saat tab tersembunyi. --}}
<script>
    (() => {
        const root = document.querySelector('[data-sstock-hero]'), cv = root?.querySelector('.sstock-hero__fx');
        if (!cv || window.sstockGaya.reduced) return;
        const ctx = cv.getContext('2d'), mouse = {x: -999, y: -999}, dots = [], tok = n => getComputedStyle(root).getPropertyValue(n).trim() || '#14b8a6', accent = tok('--dl-accent'), warm = tok('--dl-amber');   // warna ikut tema (token --dl-*)
        let w = 0, h = 0, raf = 0;
        const size = () => {
            const r = root.getBoundingClientRect(), d = devicePixelRatio || 1;
            w = r.width; h = r.height; cv.width = w * d; cv.height = h * d; ctx.setTransform(d, 0, 0, d, 0, 0);
            dots.length = 0;
            for (let i = 0, n = Math.min(70, Math.round(w * h / 9000)); i < n; i++) dots.push({x: Math.random() * w, y: Math.random() * h, vx: (Math.random() - .5) * .35, vy: (Math.random() - .5) * .35, r: 1 + Math.random() * 1.6});
        };
        const draw = () => {
            ctx.clearRect(0, 0, w, h);
            for (const a of dots) {
                a.x += a.vx; a.y += a.vy;
                if (a.x < 0 || a.x > w) a.vx *= -1;
                if (a.y < 0 || a.y > h) a.vy *= -1;
                const dm = Math.hypot(a.x - mouse.x, a.y - mouse.y);
                if (dm < 150) { a.x += (a.x - mouse.x) * .004; a.y += (a.y - mouse.y) * .004; }
                ctx.globalAlpha = .7; ctx.fillStyle = accent; ctx.beginPath(); ctx.arc(a.x, a.y, a.r, 0, 6.283); ctx.fill();
                if (dm < 170) { ctx.globalAlpha = .55 * (1 - dm / 170); ctx.strokeStyle = warm; ctx.beginPath(); ctx.moveTo(a.x, a.y); ctx.lineTo(mouse.x, mouse.y); ctx.stroke(); }
                for (const b of dots) {
                    const d = Math.hypot(a.x - b.x, a.y - b.y);
                    if (d < 105 && a < b) { ctx.globalAlpha = .3 * (1 - d / 105); ctx.strokeStyle = accent; ctx.beginPath(); ctx.moveTo(a.x, a.y); ctx.lineTo(b.x, b.y); ctx.stroke(); }
                }
            }
            raf = requestAnimationFrame(draw);
        };
        const run = () => { cancelAnimationFrame(raf); if (window.sstockGaya.get() === 'baru' && !document.hidden) { size(); raf = requestAnimationFrame(draw); } };
        root.addEventListener('pointermove', e => { const r = root.getBoundingClientRect(); mouse.x = e.clientX - r.left; mouse.y = e.clientY - r.top; });
        root.addEventListener('pointerleave', () => { mouse.x = mouse.y = -999; });
        new ResizeObserver(run).observe(root);
        addEventListener('sstock-gaya', run); document.addEventListener('visibilitychange', run);
        if (window.sstockGaya.get() === 'baru') root.querySelectorAll('.sstock-hero__num').forEach(b => {
            const end = parseInt(b.textContent.replace(/\D/g, ''), 10), t0 = performance.now();
            if (isNaN(end)) return;
            const tick = t => { const k = Math.min(1, (t - t0) / 1000); b.textContent = Math.round(end * (1 - Math.pow(1 - k, 3))).toLocaleString('id-ID'); if (k < 1) requestAnimationFrame(tick); };
            requestAnimationFrame(tick);
        });
    })();
</script>
