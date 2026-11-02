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
