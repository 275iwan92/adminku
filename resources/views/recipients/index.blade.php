{{-- REMARK
 Tujuan       : Daftar master Penerima barang, dua tampilan lewat segmen Kartu / Daftar (bawaan Kartu, pilihan diingat per pengguna di browser): Kartu = kartu putih seperti Karyawan (bilah warna menurut departemen, inisial bulat, nama,
                kode karyawan bila terhubung, chip departemen / status / jumlah dokumen) dengan tab Semua / Nonaktif, segmen departemen, dan kotak cari; Daftar = grid DevLib (tombol aktif/nonaktif, ekspor).
 Sumber hulu  : RecipientController@index.
 Input        : rows, view (cards | list), departments, counts.
 Output       : kartu (klik membuka form penerima) atau x-dl2::grid.
 Dipakai oleh : route recipients.index (menu Data Master > Penerima).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Recipients'])
@section('body')
    @include('partials.flash')
    <div class="em-top">
        @if ($view === 'cards')
            <x-dl2::button class="sstock-btn-teal" variant="primary" icon="fa-plus" label="Penerima" :href="route('recipients.create')" />
            <div class="em-pills" id="rc-pills">
                <button type="button" class="em-pill em-pill--blue is-active" data-f="all">Semua <b>{{ $counts['all'] }}</b></button>
                <button type="button" class="em-pill em-pill--gray" data-f="off">Nonaktif <b>{{ $counts['off'] }}</b></button>
            </div>
            @if (count($departments) > 1)
                <div class="ak-seg" id="rc-dept"><button type="button" class="ak-seg__opt is-active" data-d="">Semua departemen</button>@foreach ($departments as $d)<button type="button" class="ak-seg__opt" data-d="{{ $d }}">{{ $d }}</button>@endforeach</div>
            @endif
            <input type="search" class="em-search" id="rc-q" placeholder="Cari nama, departemen..." aria-label="Cari penerima">
        @endif
        <span class="em-spacer"></span>
        <div class="ak-seg" id="rc-view">
            <a class="ak-seg__opt {{ $view === 'cards' ? 'is-active' : '' }}" href="{{ route('recipients.index') }}" data-v="cards"><i class="fa-solid fa-table-cells-large"></i> Kartu</a>
            <a class="ak-seg__opt {{ $view === 'list' ? 'is-active' : '' }}" href="{{ route('recipients.index', ['view' => 'list']) }}" data-v="list"><i class="fa-solid fa-list"></i> Daftar</a>
        </div>
    </div>

    @if ($view === 'cards')
        <div class="em-cards">
            @foreach ($rows as $r)
                @php($isEmp = $r['employee'] !== '—')
                @if ($isEmp)
                    {{-- Sudah karyawan: kartu bentuk Karyawan (bilah, inisial bulat) --}}
                    <a class="em-card rc-card {{ $r['active'] ? '' : 'is-exited' }}" href="{{ url('recipients/'.$r['id']) }}" data-dept="{{ $r['department'] }}" data-active="{{ $r['active'] ? 1 : 0 }}" data-q="{{ mb_strtolower($r['name'].' '.$r['department'].' '.$r['employee']) }}">
                        <span class="em-bar" style="background: hsl({{ $r['hue'] }} 55% 42%)"></span>
                        <span class="em-photo em-photo--ini" style="background: hsl({{ $r['hue'] }} 50% 38%)">{{ $r['initials'] }}</span>
                        <span class="em-name">{{ $r['name'] }}</span>
                        <span class="em-sub">Karyawan · {{ $r['employee'] }}</span>
                        <span class="em-chips">
                            <span class="em-chip" style="background: hsl({{ $r['hue'] }} 70% 92%); color: hsl({{ $r['hue'] }} 55% 26%)">{{ $r['department'] }}</span>
                            <span class="em-chip {{ $r['active'] ? 'em-chip--ok' : 'em-chip--gray' }}">{{ $r['active'] ? 'Aktif' : 'Nonaktif' }}</span>
                        </span>
                        <span class="em-foot">{{ $r['documents'] }} dokumen pengeluaran</span>
                    </a>
                @else
                    {{-- Belum karyawan: kartu pita pastel bergaris putus-putus (bentuk lain supaya mudah dibedakan) --}}
                    <a class="em-card rc-card rc-plain {{ $r['active'] ? '' : 'is-exited' }}" href="{{ url('recipients/'.$r['id']) }}" data-dept="{{ $r['department'] }}" data-active="{{ $r['active'] ? 1 : 0 }}" data-q="{{ mb_strtolower($r['name'].' '.$r['department']) }}">
                        <span class="rc-head" style="background: hsl({{ $r['hue'] }} 70% 90%); color: hsl({{ $r['hue'] }} 55% 24%)"><span><i class="fa-solid fa-user"></i> {{ $r['name'] }}</span><small>{{ $r['department'] }}</small></span>
                        <span class="rc-num">{{ $r['documents'] }}</span>
                        <span class="em-sub">dokumen pengeluaran</span>
                        <span class="em-chips"><span class="em-chip em-chip--gray">Bukan karyawan</span>@unless ($r['active'])<span class="em-chip em-chip--gray">Nonaktif</span>@endunless</span>
                    </a>
                @endif
            @endforeach
            @if (! count($rows))<p class="ak-note">Belum ada penerima.</p>@endif
        </div>
        <p class="ak-note" id="rc-empty" hidden>Tidak ada penerima yang cocok.</p>
    @else
        <x-dl2::grid id="recipients" :rows="$rows" actions="edit" :new-url="route('recipients.create')" :open-url="url('recipients/{id}')" :toggle-url="url('recipients/{id}/toggle')"
            search="Search name, department..."
            :phone-cards="['title' => 'name', 'sub' => 'department', 'meta' => ['documents']]"
            :columns="[
                ['key' => 'name', 'label' => 'Recipient', 'type' => 'identity', 'code' => 'department', 'box' => false],
                ['key' => 'employee', 'label' => 'Karyawan'],
                ['key' => 'documents', 'label' => 'Issuance Documents', 'type' => 'number'],
            ]"
            :stats="[['label' => 'All recipients'], ['label' => 'Inactive', 'where' => ['active' => false], 'tone' => 'warn']]" />
    @endif

    @include('employees._cards-style')
    <style>
        .rc-plain { padding: 0 0 12px; gap: 2px; border-style: dashed; border-radius: 6px; }
        .rc-plain > :not(.rc-head) { margin-left: 14px; margin-right: 14px; }
        .rc-head { display: flex; justify-content: space-between; align-items: center; gap: 8px; padding: 9px 14px; font-weight: 700; font-size: 14px; border-radius: 5px 5px 0 0; margin-bottom: 8px; }
        .rc-head small { font-weight: 600; font-size: 11.5px; opacity: .85; }
        .rc-num { font-size: 26px; font-weight: 700; color: var(--dl2-ink, #111); }
    </style>
    <script>
        window.akReady(() => {
            // Pilihan tampilan diingat per pengguna (browser): tanpa ?view= di alamat, tampilan tersimpan dipakai.
            const KEY = 'jays-recipients-view';
            const current = @json($view), hasQuery = new URLSearchParams(location.search).has('view');
            try {
                const saved = localStorage.getItem(KEY);
                if (! hasQuery && saved === 'list' && current === 'cards') { location.replace(@json(route('recipients.index', ['view' => 'list']))); return; }
                document.getElementById('rc-view')?.addEventListener('click', (e) => { const a = e.target.closest('[data-v]'); if (a) { localStorage.setItem(KEY, a.dataset.v); } });
                if (hasQuery && current === 'cards') { localStorage.setItem(KEY, 'cards'); }
            } catch (e) { /* tanpa penyimpanan: abaikan */ }

            const cards = [...document.querySelectorAll('.rc-card')];
            if (cards.length) {
                const st = { f: 'all', d: '', q: '' };
                const apply = () => {
                    let shown = 0;
                    cards.forEach((c) => {
                        const ok = (st.f === 'all' || c.dataset.active === '0') && (! st.d || c.dataset.dept === st.d) && (! st.q || c.dataset.q.includes(st.q));
                        c.hidden = ! ok; shown += ok ? 1 : 0;
                    });
                    document.getElementById('rc-empty').hidden = shown > 0;
                };
                document.getElementById('rc-pills')?.addEventListener('click', (e) => {
                    const b = e.target.closest('[data-f]'); if (! b) { return; }
                    st.f = b.dataset.f; document.querySelectorAll('#rc-pills .em-pill').forEach((x) => x.classList.toggle('is-active', x === b)); apply();
                });
                document.getElementById('rc-dept')?.addEventListener('click', (e) => {
                    const b = e.target.closest('[data-d]'); if (! b) { return; }
                    st.d = b.dataset.d; document.querySelectorAll('#rc-dept [data-d]').forEach((x) => x.classList.toggle('is-active', x === b)); apply();
                });
                document.getElementById('rc-q')?.addEventListener('input', (e) => { st.q = e.target.value.trim().toLowerCase(); apply(); });
            }
        });
    </script>
@endsection
