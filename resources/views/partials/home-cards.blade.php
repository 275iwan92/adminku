{{-- REMARK
 Tujuan       : Kartu modul Beranda global (desktop dan HP): judul modul, tiga angka (berwarna koral bila perlu perhatian), dan tautan ke layar pembuka modul. Kartu hanya untuk modul yang boleh dilihat pengguna; tanpa satu pun kartu = keterangan singkat.
                Desktop: kartu membuka tab kerja Layer Shell (data-open-tab); HP: tautan ke tampilan HP modul.
 Sumber hulu  : App\Support\HomeCards::page (cards).
 Input        : cards (code, title, icon, tone, link, stats[value, label, alert, title]), phone (true = tautan HP).
 Output       : kartu dengan kelas sstock-mod* (CSS di resources/css/app.css).
 Dipakai oleh : home (desktop), hp.home.
 Status       : Aktif.
--}}
@if ($cards)
    {{-- Saklar gerak: Animasi (bawaan) / Tanpa animasi; keduanya memakai tampilan kartu baru (kelas is-baru), beda hanya animasi dan efek JS. Pilihan diingat per browser (localStorage) atau paksa lewat ?gaya=baru / ?gaya=lama; tanpa pilihan, pengguna yang memilih "kurangi gerakan" di sistemnya mendapat Tanpa animasi (logika di partial home-hero). CSS polos lama (.sstock-mod tanpa is-baru) tetap ada di app.css. --}}
    <div class="sstock-gaya" role="group" aria-label="Gaya tampilan ringkasan">
        <button type="button" data-gaya="baru">Animasi</button><button type="button" data-gaya="lama">Tanpa animasi</button>
    </div>
    <div class="sstock-mods is-baru" id="sstock-mods">
        @foreach ($cards as $c)
            @php $link = $phone ? ['a', 'href="'.e(\App\Support\HpShell::url($c['code'])).'"'] : ['section', 'data-open-tab="'.e($c['code']).'" role="link" tabindex="0"']; @endphp
            <{{ $link[0] }} class="sstock-mod sstock-mod--{{ $c['tone'] }} {{ $phone ? '' : 'sstock-link' }}" {!! $link[1] !!} aria-label="{{ $c['title'] }}: {{ $c['link'] }}">
                <h3 class="sstock-mod__title"><i class="{{ $c['icon'] }}" aria-hidden="true"></i> <span>{{ $c['title'] }}</span></h3>
                <ul class="sstock-mod__stats">
                    @foreach ($c['stats'] as $s)
                        <li><b class="{{ ($s['alert'] ?? false) ? 'is-alert' : '' }}" @isset($s['title']) title="{{ $s['title'] }}" @endisset>{{ $s['value'] }}</b><span>{{ $s['label'] }}</span></li>
                    @endforeach
                </ul>
                @if (array_sum(array_column($c['bar'], 0)) > 0)
                    <div class="sstock-mod__bar" role="img" aria-label="{{ collect($c['bar'])->filter(fn ($b) => $b[0] > 0)->map(fn ($b) => $b[2].': '.\Devlib2\Locale::number($b[0], 0))->implode('; ') }}">
                        @foreach ($c['bar'] as [$v, $k, $t])
                            @if ($v > 0)<u class="is-{{ $k }}" style="flex: {{ $v }}" title="{{ $t }}"></u>@endif
                        @endforeach
                    </div>
                @endif
                <span class="sstock-mod__go">{{ $c['link'] }} <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span>
            </{{ $link[0] }}>
        @endforeach
    </div>
    <script>
        (() => {
            const box = document.getElementById('sstock-mods'), btns = document.querySelectorAll('.sstock-gaya [data-gaya]');
            const show = () => btns.forEach(b => b.setAttribute('aria-pressed', b.dataset.gaya === sstockGaya.get()));
            // Desktop: saklar pindah ke bilah tab (tempat tulisan "Belum ada menu terbuka" dulu); hilang sendiri begitu ada tab terbuka (CSS :not(:empty)).
            const bar = document.querySelector('[data-dl2-tabbar]'), sw = document.querySelector('.sstock-gaya');
            if (bar && sw) { bar.parentElement.style.position = 'relative'; bar.after(sw); sw.classList.add('is-bar'); }
            addEventListener('sstock-gaya', show); show();
            btns.forEach(b => b.addEventListener('click', () => sstockGaya.set(b.dataset.gaya, true)));
            // Efek gaya baru (dilewati bila pengguna memilih kurangi gerakan): kartu miring 3D + sorot cahaya mengikuti kursor, angka menghitung naik saat Beranda dibuka.
            if (sstockGaya.reduced || sstockGaya.get() !== 'baru') return;
            box.querySelectorAll('.sstock-mod').forEach(c => {
                c.addEventListener('pointermove', e => {
                    const r = c.getBoundingClientRect(), x = (e.clientX - r.left) / r.width, y = (e.clientY - r.top) / r.height;
                    c.style.setProperty('--mx', x * 100 + '%'); c.style.setProperty('--my', y * 100 + '%');
                    c.style.setProperty('--ry', (x - .5) * 6 + 'deg'); c.style.setProperty('--rx', (.5 - y) * 5 + 'deg');
                });
                c.addEventListener('pointerleave', () => { c.style.removeProperty('--rx'); c.style.removeProperty('--ry'); });
            });
            box.querySelectorAll('.sstock-mod__stats b').forEach(b => {
                const m = b.textContent.match(/\d[\d.]*(?:,(\d+))?/);
                if (!m) return;
                const dec = (m[1] || '').length, end = parseFloat(m[0].replace(/\./g, '').replace(',', '.')), t0 = performance.now();
                const fmt = v => v.toLocaleString('id-ID', {minimumFractionDigits: dec, maximumFractionDigits: dec});
                const tick = t => { const k = Math.min(1, (t - t0) / 900); b.textContent = b.dataset.f.replace(m[0], fmt(end * (1 - Math.pow(1 - k, 3)))); if (k < 1) requestAnimationFrame(tick); };
                b.dataset.f = b.textContent; requestAnimationFrame(tick);
            });
        })();
    </script>
@else
    <p class="sstock-mods-empty">Belum ada ringkasan untuk hak akses Anda. {{ $phone ? 'Buka menu di pojok kanan atas untuk memulai.' : 'Pilih menu di sebelah kiri untuk memulai.' }}</p>
@endif
