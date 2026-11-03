{{-- REMARK
 Tujuan       : Layar Karyawan (HRE) dengan dua tampilan lewat segmen Kartu / Daftar (bawaan Kartu; pilihan diingat per pengguna di browser): Kartu = grid kartu putih (bilah warna menurut bagian, foto profil bulat menimpa bilah, nama, jabatan + kode, chip bagian / status kerja / status, SP aktif, peringatan SIM / kontrak, tanggal masuk dan masa kerja);
                Daftar = grid DevLib dengan foto bulat di kolom pertama. Tab Semua / Perlu perhatian / Keluar sebagai pil berwarna dengan penghitung, segmen bagian, dan kotak cari (tampilan Kartu). NIK tidak tampil di sini.
 Sumber hulu  : EmployeeController@index.
 Input        : rows, view (cards | list), departments, counts.
 Output       : kartu (klik membuka form karyawan) atau x-dl::grid.
 Dipakai oleh : route employees.index (menu HR > Karyawan).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Karyawan'])
@section('body')
    @include('partials.flash')
    @php
        $hue = fn (string $s) => abs(crc32($s)) % 360;
        $tones = ['tetap' => 'ok', 'kontrak' => 'warn', 'harian' => 'info', 'magang' => 'violet'];
    @endphp
    <div class="em-top">
        @if ($view === 'cards' && auth()->user()->canWrite('HRE'))
            <x-dl::button class="sstock-btn-teal" variant="primary" icon="fa-plus" label="Karyawan" :href="route('employees.create')" />
        @endif
        @if ($view === 'cards')
            <div class="em-pills" id="em-pills">
                <button type="button" class="em-pill em-pill--blue is-active" data-f="all">Semua <b>{{ $counts['all'] }}</b></button>
                <button type="button" class="em-pill em-pill--coral" data-f="attn">Perlu perhatian <b>{{ $counts['attn'] }}</b></button>
                <button type="button" class="em-pill em-pill--gray" data-f="exited">Keluar <b>{{ $counts['exited'] }}</b></button>
            </div>
            @if (count($departments) > 1)
                <div class="dl-segment" id="em-dept"><button type="button" class="dl-segment__opt is-active" data-d="">Semua bagian</button>@foreach ($departments as $d)<button type="button" class="dl-segment__opt" data-d="{{ $d }}">{{ $d }}</button>@endforeach</div>
            @endif
            <input type="search" class="em-search" id="em-q" placeholder="Cari nama, kode, jabatan..." aria-label="Cari karyawan">
        @endif
        <span class="em-spacer"></span>
        <div class="dl-segment" id="em-view">
            <a class="dl-segment__opt {{ $view === 'cards' ? 'is-active' : '' }}" href="{{ route('employees.index') }}" data-v="cards"><i class="fa-solid fa-table-cells-large"></i> Kartu</a>
            <a class="dl-segment__opt {{ $view === 'list' ? 'is-active' : '' }}" href="{{ route('employees.index', ['view' => 'list']) }}" data-v="list"><i class="fa-solid fa-list"></i> Daftar</a>
        </div>
    </div>

    @if ($view === 'cards')
        <div class="em-cards" id="em-cards">
            @foreach ($rows as $r)
                @php
                    $h = $hue($r['department']);
                    $att = $r['attention'];
                    $attRed = $att && (str_starts_with($att, 'Kontrak') || str_contains($att, 'lewat'));
                @endphp
                <a class="em-card {{ $r['active'] ? '' : 'is-exited' }}" href="{{ url('employees/'.$r['id']) }}" data-dept="{{ $r['department'] }}" data-attn="{{ $r['attn'] ? 1 : 0 }}" data-active="{{ $r['active'] ? 1 : 0 }}" data-q="{{ mb_strtolower($r['name'].' '.$r['code'].' '.$r['position'].' '.$r['department']) }}">
                    <span class="em-bar" style="background: hsl({{ $h }} 55% 42%)"></span>
                    @if ($r['photo'])<img class="em-photo" src="{{ $r['photo'] }}" alt="">@else<span class="em-photo em-photo--ini" style="background: hsl({{ $h }} 50% 38%)">{{ $r['initials'] }}</span>@endif
                    <span class="em-name">{{ $r['name'] }}</span>
                    <span class="em-sub">{{ $r['position'] ?: '-' }} · {{ $r['code'] }}</span>
                    <span class="em-chips">
                        <span class="em-chip" style="background: hsl({{ $h }} 70% 92%); color: hsl({{ $h }} 55% 26%)">{{ $r['department'] }}</span>
                        <span class="em-chip em-chip--{{ $tones[$r['employment_key']] ?? 'ok' }}">{{ $r['employment'] }}</span>
                        <span class="em-chip {{ $r['active'] ? 'em-chip--ok' : 'em-chip--gray' }}">{{ $r['active'] ? \App\Models\Employee::WORK_STATUSES[$r['work_status']] : 'Keluar' }}</span>
                        @if ($r['sp'])<span class="em-chip em-chip--red">{{ $r['sp'] }} aktif</span>@endif
                    </span>
                    @if ($att)<span class="em-warn {{ $attRed ? 'em-warn--red' : '' }}"><i class="fa-solid fa-triangle-exclamation"></i> {{ $att }}</span>@endif
                    <span class="em-foot">Masuk {{ $r['joined'] }} · {{ $r['tenure'] }}</span>
                </a>
            @endforeach
            @if (! count($rows))<p class="dl-note">Belum ada karyawan.</p>@endif
        </div>
        <p class="dl-note" id="em-empty" hidden>Tidak ada karyawan yang cocok.</p>
    @else
        <x-dl::grid id="employees" :rows="$rows" actions="edit" :new-url="auth()->user()->canWrite('HRE') ? route('employees.create') : null" :open-url="url('employees/{id}')"
            search="Cari kode, nama, jabatan, bagian..."
            :phone-cards="['title' => 'name', 'sub' => 'code', 'meta' => ['position', 'department', 'status', 'attention']]"
            :columns="[
                ['key' => 'name', 'label' => 'Karyawan', 'type' => 'identity', 'code' => 'code', 'box' => true],
                ['key' => 'position', 'label' => 'Jabatan'],
                ['key' => 'department', 'label' => 'Bagian'],
                ['key' => 'employment', 'label' => 'Status kerja'],
                ['key' => 'join_date', 'label' => 'Masuk', 'type' => 'date'],
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'sp', 'label' => 'SP aktif'],
                ['key' => 'attention', 'label' => 'Perhatian'],
            ]"
            :stats="[['label' => 'Semua karyawan'], ['label' => 'Perlu perhatian', 'where' => ['attn' => true], 'tone' => 'warn'], ['label' => 'Keluar', 'where' => ['active' => false]]]" />
    @endif

    <style>
        .em-top { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin: 0 0 12px; }
        .em-spacer { flex: 1; }
        .em-pills { display: inline-flex; gap: 6px; flex-wrap: wrap; }
        .em-pill { border: 0; border-radius: 999px; padding: 6px 14px; font: inherit; font-size: 12.5px; cursor: pointer; background: var(--dl-chip, #eee); color: var(--dl-ink, #333); }
        .em-pill b { margin-left: 4px; background: rgba(255,255,255,.55); border-radius: 999px; padding: 0 7px; font-weight: 700; }
        .em-pill--blue.is-active { background: #2f6fd0; color: #fff; } .em-pill--coral.is-active { background: #d85a30; color: #fff; } .em-pill--gray.is-active { background: #6b6b66; color: #fff; }
        .em-pill:not(.is-active).em-pill--blue { background: #e3edfb; color: #1c4a96; } .em-pill:not(.is-active).em-pill--coral { background: #fbe6de; color: #8a3414; } .em-pill:not(.is-active).em-pill--gray { background: #ececea; color: #4a4a47; }
        .em-search { padding: 6px 10px; border: 1px solid var(--dl-border, #ccc); border-radius: 8px; background: var(--dl-surface, #fff); color: var(--dl-ink, #222); font: inherit; font-size: 12.5px; min-width: 200px; }
        .em-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(210px, 1fr)); gap: 14px; }
        .em-card { position: relative; display: flex; flex-direction: column; gap: 4px; padding: 0 14px 12px; background: var(--dl-surface, #fff); border: 1px solid var(--dl-border, #e2e2e2); border-radius: 14px; text-decoration: none; color: inherit; overflow: hidden; transition: box-shadow .15s, transform .15s; }
        .em-card:hover { box-shadow: 0 6px 18px rgba(0,0,0,.12); transform: translateY(-2px); }
        .em-card[hidden] { display: none; }
        .em-card.is-exited { opacity: .72; }
        .em-bar { display: block; height: 46px; margin: 0 -14px; }
        .em-photo { width: 68px; height: 68px; border-radius: 50%; object-fit: cover; border: 3px solid var(--dl-surface, #fff); margin-top: -34px; background: #e8eef3; display: block; }
        .em-photo--ini { display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; font-size: 22px; }
        .em-name { font-weight: 700; font-size: 15px; margin-top: 4px; color: var(--dl-ink-strong, #111); }
        .em-sub { font-size: 12px; color: var(--dl-muted, #666); }
        .em-chips { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 4px; }
        .em-chip { font-size: 11px; border-radius: 999px; padding: 2px 9px; background: #eee; color: #333; }
        .em-chip--ok { background: #e2f2e0; color: #1f5a1b; } .em-chip--warn { background: #fbefd5; color: #7a4d06; } .em-chip--info { background: #e1ecf9; color: #1c4a96; } .em-chip--violet { background: #ece9fa; color: #473f9c; }
        .em-chip--gray { background: #ececea; color: #4a4a47; } .em-chip--red { background: #fbe0e0; color: #8e1f1f; font-weight: 700; }
        .em-warn { font-size: 11.5px; border-radius: 8px; padding: 3px 8px; background: #fbefd5; color: #7a4d06; margin-top: 2px; }
        .em-warn--red { background: #fbe0e0; color: #8e1f1f; }
        .em-foot { font-size: 11.5px; color: var(--dl-muted, #666); margin-top: 4px; border-top: 1px solid var(--dl-border, #eee); padding-top: 6px; }
        .em-gphoto { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; vertical-align: middle; margin-right: 8px; border: 1px solid var(--dl-border, #ddd); }
        [data-dl^="x-dl::grid id=employees"] .dl-avatar-id { vertical-align: middle; }
        @media (max-width: 600px) { .em-cards { grid-template-columns: 1fr; } .em-search { width: 100%; } }
    </style>
    <script>
        (() => {
            // Pilihan tampilan diingat per pengguna (browser): tanpa ?view= di alamat, tampilan tersimpan dipakai.
            const KEY = 'jays-employees-view';
            const current = @json($view), hasQuery = new URLSearchParams(location.search).has('view');
            try {
                const saved = localStorage.getItem(KEY);
                if (! hasQuery && saved === 'list' && current === 'cards') { location.replace(@json(route('employees.index', ['view' => 'list']))); return; }
                document.getElementById('em-view')?.addEventListener('click', (e) => { const a = e.target.closest('[data-v]'); if (a) { localStorage.setItem(KEY, a.dataset.v); } });
                if (hasQuery && current === 'cards') { localStorage.setItem(KEY, 'cards'); }
            } catch (e) { /* tanpa penyimpanan: abaikan */ }

            // Tampilan Kartu: tab (pil), segmen bagian, kotak cari.
            const cards = [...document.querySelectorAll('.em-card')];
            if (cards.length) {
                const st = { f: 'all', d: '', q: '' };
                const apply = () => {
                    let shown = 0;
                    cards.forEach((c) => {
                        const ok = (st.f === 'all' ? true : st.f === 'attn' ? c.dataset.attn === '1' : c.dataset.active === '0')
                            && (! st.d || c.dataset.dept === st.d) && (! st.q || c.dataset.q.includes(st.q));
                        c.hidden = ! ok; shown += ok ? 1 : 0;
                    });
                    document.getElementById('em-empty').hidden = shown > 0;
                };
                document.getElementById('em-pills')?.addEventListener('click', (e) => {
                    const b = e.target.closest('[data-f]'); if (! b) { return; }
                    st.f = b.dataset.f; document.querySelectorAll('#em-pills .em-pill').forEach((x) => x.classList.toggle('is-active', x === b)); apply();
                });
                document.getElementById('em-dept')?.addEventListener('click', (e) => {
                    const b = e.target.closest('[data-d]'); if (! b) { return; }
                    st.d = b.dataset.d; document.querySelectorAll('#em-dept [data-d]').forEach((x) => x.classList.toggle('is-active', x === b)); apply();
                });
                document.getElementById('em-q')?.addEventListener('input', (e) => { st.q = e.target.value.trim().toLowerCase(); apply(); });
            }

            // Tampilan Daftar: foto bulat 36px di kolom pertama (grid DevLib belum punya foto di kolom identitas, jadi disisipkan setelah digambar).
            const PHOTOS = @json(collect($rows)->mapWithKeys(fn ($r) => [$r['code'] => $r['photo']])->filter()->all());
            const decorate = () => {
                document.querySelectorAll('[data-dl^="x-dl::grid id=employees"] tbody tr').forEach((tr) => {
                    const box = tr.querySelector('.dl-avatar-id'); if (! box || tr.querySelector('.em-gphoto')) { return; }
                    const photo = PHOTOS[box.querySelector('small')?.textContent.trim()]; if (! photo) { return; }
                    box.insertAdjacentHTML('beforebegin', `<img class="em-gphoto" src="${photo}" alt="">`);
                });
            };
            const grid = document.querySelector('[data-dl^="x-dl::grid id=employees"]');
            if (grid) { new MutationObserver(decorate).observe(grid, { childList: true, subtree: true }); decorate(); }
        })();
    </script>
@endsection
