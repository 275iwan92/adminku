{{-- REMARK
 Tujuan       : Layar Karyawan (HRE) dengan dua tampilan lewat segmen Kartu / Daftar (bawaan Kartu; pilihan diingat per pengguna di browser): Kartu = grid kartu putih (bilah warna menurut bagian, foto profil bulat menimpa bilah, nama, jabatan + kode, chip bagian / status kerja / status, SP aktif, peringatan SIM / kontrak, tanggal masuk dan masa kerja);
                Daftar = grid DevLib dengan foto bulat di kolom pertama. Tab Semua / Perlu perhatian / Keluar sebagai pil berwarna dengan penghitung, segmen bagian, dan kotak cari (tampilan Kartu). NIK tidak tampil di sini.
 Sumber hulu  : EmployeeController@index.
 Input        : rows, view (cards | list), departments, counts.
 Output       : kartu (klik membuka form karyawan) atau x-dl2::grid.
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
            <x-dl2::button class="sstock-btn-teal" variant="primary" icon="fa-plus" label="Karyawan" :href="route('employees.create')" />
        @endif
        @if ($view === 'cards')
            <div class="em-pills" id="em-pills">
                <button type="button" class="em-pill em-pill--blue is-active" data-f="all">Semua <b>{{ $counts['all'] }}</b></button>
                <button type="button" class="em-pill em-pill--coral" data-f="attn">Perlu perhatian <b>{{ $counts['attn'] }}</b></button>
                <button type="button" class="em-pill em-pill--gray" data-f="exited">Keluar <b>{{ $counts['exited'] }}</b></button>
            </div>
            @if (count($departments) > 1)
                <div class="ak-seg" id="em-dept"><button type="button" class="ak-seg__opt is-active" data-d="">Semua bagian</button>@foreach ($departments as $d)<button type="button" class="ak-seg__opt" data-d="{{ $d }}">{{ $d }}</button>@endforeach</div>
            @endif
            <input type="search" class="em-search" id="em-q" placeholder="Cari nama, kode, jabatan..." aria-label="Cari karyawan">
        @endif
        <span class="em-spacer"></span>
        <div class="ak-seg" id="em-view">
            <a class="ak-seg__opt {{ $view === 'cards' ? 'is-active' : '' }}" href="{{ route('employees.index') }}" data-v="cards"><i class="fa-solid fa-table-cells-large"></i> Kartu</a>
            <a class="ak-seg__opt {{ $view === 'list' ? 'is-active' : '' }}" href="{{ route('employees.index', ['view' => 'list']) }}" data-v="list"><i class="fa-solid fa-list"></i> Daftar</a>
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
            @if (! count($rows))<p class="ak-note">Belum ada karyawan.</p>@endif
        </div>
        <p class="ak-note" id="em-empty" hidden>Tidak ada karyawan yang cocok.</p>
    @else
        <x-dl2::grid id="employees" :rows="$rows" actions="edit" :new-url="auth()->user()->canWrite('HRE') ? route('employees.create') : null" :open-url="url('employees/{id}')"
            search="Cari kode, nama, jabatan, bagian..."
            :phone-cards="['title' => 'name', 'sub' => 'code', 'meta' => ['position', 'department', 'status', 'attention']]"
            :columns="[
                ['key' => 'name', 'label' => 'Karyawan', 'type' => 'identity', 'code' => 'code', 'box' => false],
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

    @include('employees._cards-style')
    <script>
        window.akReady(() => {
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
                document.querySelectorAll('[data-dl^="x-dl2::grid id=employees"] tbody tr').forEach((tr) => {
                    const box = tr.querySelector('.dl-avatar-id'); if (! box || tr.querySelector('.em-gphoto')) { return; }
                    const photo = PHOTOS[box.querySelector('small')?.textContent.trim()]; if (! photo) { return; }
                    box.insertAdjacentHTML('beforebegin', `<img class="em-gphoto" src="${photo}" alt="">`);
                });
            };
            const grid = document.querySelector('[data-dl^="x-dl2::grid id=employees"]');
            if (grid) { new MutationObserver(decorate).observe(grid, { childList: true, subtree: true }); decorate(); }
        });
    </script>
@endsection
