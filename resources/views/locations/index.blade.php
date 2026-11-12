{{-- REMARK
 Tujuan       : Daftar master Lokasi Penyimpanan: ubin per jenis (lemari, gudang tertutup, gudang terbuka, area luas, belum ditentukan); klik ubin = buka kartu lokasinya (satu terbuka sekaligus).
 Sumber hulu  : LocationController@index (rows: id, code, name, type, items, active).
 Input        : rows.
 Output       : kartu lokasi; klik kartu membuka form lokasi. Pencarian dan saringan Nonaktif lewat JS kecil di bawah.
 Dipakai oleh : route locations.index (menu Data Master > Lokasi Penyimpanan, Supervisor).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Storage Locations'])
@section('body')
    @include('partials.flash')
    @php
        $cats = [
            'lemari' => ['Lemari', 'fa-box-archive', '#ede9fe', '#4c1d95'],
            'gudang_tertutup' => ['Gudang tertutup', 'fa-warehouse', '#ccfbf1', '#115e59'],
            'gudang_terbuka' => ['Gudang terbuka', 'fa-sun', '#fef3c7', '#78350f'],
            'area_luas' => ['Area luas', 'fa-map', '#ffe4de', '#7f2a1c'],
        ];
        $byType = collect($rows)->groupBy(fn ($r) => $r['type'] ?: '');
        if ($byType->has('')) {
            $cats[''] = ['Belum ditentukan', 'fa-circle-question', '#e7ece9', '#3f4a45'];
        }
        $off = collect($rows)->where('active', false)->count();
    @endphp
    <style>
        .loc { max-width: 1040px }
        .loc-bar { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 14px }
        .loc-bar input[type=search] { flex: 1 1 220px; max-width: 360px; padding: 8px 12px; border: 1px solid var(--dl2-border); border-radius: 10px; background: var(--dl2-surface); color: var(--dl2-ink); font: inherit }
        .loc-off { border: 1px solid var(--dl2-border); background: var(--dl2-surface); color: var(--dl2-ink-soft); border-radius: 10px; padding: 7px 12px; font: inherit; font-size: .85rem; cursor: pointer }
        .loc-off[aria-pressed=true] { background: var(--ak-accent-soft); color: var(--dl2-ink); border-color: var(--dl2-primary) }
        .loc-bar .loc-new { margin-left: auto }
        .loc-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px }
        .loc-cat { background: var(--bg); color: var(--fg); border-radius: 16px; animation: loc-in .35s; transition: opacity .2s }
        .loc-cat > summary { list-style: none; cursor: pointer; display: flex; flex-direction: column; align-items: center; gap: 4px; padding: 18px 8px; text-align: center }
        .loc-cat > summary::-webkit-details-marker { display: none }
        .loc-cat .loc-ico { font-size: 2rem; line-height: 1 }
        .loc-cat b { font-size: 1rem }
        .loc-cat small { opacity: .8 }
        .loc-cat[open] { grid-column: 1 / -1 }
        .loc-cat[open] > summary { flex-direction: row; gap: 10px; padding: 8px 14px; text-align: left }
        .loc-cat[open] .loc-ico { font-size: 34px; width: 34px; text-align: center }
        .loc-cat[open] > summary::after { content: '▲'; margin-left: auto; font-size: .8rem }
        .loc-grid:has(details[open]) .loc-cat:not([open]) { opacity: .55 }
        .loc-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 10px; padding: 4px 12px 14px; animation: loc-in .3s }
        .loc-card { display: flex; flex-direction: column; gap: 4px; padding: 10px 12px; background: var(--dl2-surface); color: var(--dl2-ink); border: 1px solid var(--dl2-border); border-radius: 12px; text-decoration: none; transition: box-shadow .15s, transform .15s }
        .loc-card:hover { box-shadow: 0 2px 10px rgba(0, 0, 0, .1); transform: translateY(-1px) }
        .loc-card b { font-size: .95rem }
        .loc-card code { font-size: .75rem; color: var(--dl2-ink-soft) }
        .loc-pill { align-self: flex-start; font-size: .75rem; padding: 1px 9px; border-radius: 99px; background: var(--bg); color: var(--fg) }
        .loc-pill--empty, .loc-pill--off { background: var(--dl2-surface-2); color: var(--dl2-ink-soft) }
        .loc-card--off { opacity: .6 }
        .loc-none { padding: 6px 14px 14px; opacity: .8 }
        @keyframes loc-in { from { opacity: 0; transform: translateY(-6px) } to { opacity: 1; transform: none } }
        @media (max-width: 900px) { .loc-grid { grid-template-columns: repeat(2, 1fr) } }
        @media (prefers-reduced-motion: reduce) { .loc-cat, .loc-cards { animation: none } }
    </style>
    <div class="loc">
        <div class="loc-bar">
            <input type="search" id="loc-q" placeholder="Cari kode atau nama..." aria-label="Cari kode atau nama...">
            <button type="button" class="loc-off" id="loc-off" aria-pressed="false">Nonaktif ({{ $off }})</button>
            <x-dl2::button class="loc-new" variant="primary" size="sm" :href="route('locations.create')" label="+ New Record" />
        </div>
        <div class="loc-grid">
            @foreach ($cats as $key => [$label, $icon, $bg, $fg])
                @php $list = $byType->get((string) $key, collect()); @endphp
                <details name="k" class="loc-cat" style="--bg: {{ $bg }}; --fg: {{ $fg }}">
                    <summary><i class="fa-solid {{ $icon }} loc-ico"></i><b>{{ $label }}</b><small><span data-n>{{ $list->count() }}</span> lokasi</small></summary>
                    @if ($list->isEmpty())
                        <div class="loc-none">Belum ada lokasi pada jenis ini.</div>
                    @else
                        <div class="loc-cards">
                            @foreach ($list as $r)
                                <a href="{{ url('locations/'.$r['id']) }}" class="loc-card {{ $r['active'] ? '' : 'loc-card--off' }}" data-off="{{ $r['active'] ? 0 : 1 }}" data-q="{{ mb_strtolower($r['code'].' '.$r['name']) }}">
                                    <b>{{ $r['name'] }}</b>
                                    <code>{{ $r['code'] }}</code>
                                    <span style="display:flex;gap:6px;flex-wrap:wrap">
                                        @if ($r['items'] > 0)<span class="loc-pill">{{ $r['items'] }} barang</span>@else<span class="loc-pill loc-pill--empty">Kosong</span>@endif
                                        @unless ($r['active'])<span class="loc-pill loc-pill--off">Nonaktif</span>@endunless
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </details>
            @endforeach
        </div>
    </div>
    <script>
        window.akReady(() => {
            const q = document.getElementById('loc-q'), off = document.getElementById('loc-off');
            const apply = () => {
                const t = q.value.trim().toLowerCase(), onlyOff = off.getAttribute('aria-pressed') === 'true';
                let first = null;
                document.querySelectorAll('.loc-cat').forEach(cat => {
                    let n = 0;
                    cat.querySelectorAll('.loc-card').forEach(c => {
                        const show = c.dataset.q.includes(t) && (!onlyOff || c.dataset.off === '1');
                        c.hidden = !show; n += show;
                    });
                    cat.querySelector('[data-n]').textContent = n;
                    cat.hidden = (t || onlyOff) && n === 0;
                    if (!cat.hidden && n && !first) first = cat;
                });
                if (t && first && !document.querySelector('.loc-cat[open]:not([hidden])')) first.open = true;
            };
            q.addEventListener('input', apply);
            off.addEventListener('click', () => { off.setAttribute('aria-pressed', off.getAttribute('aria-pressed') !== 'true'); apply(); });
        });
    </script>
@endsection
