{{-- REMARK
 Tujuan       : Daftar master berbentuk kartu per kelompok yang bisa dibuka (gaya Lokasi): kartu kelompok (ikon, nama, jumlah) membuka daftar kartu isinya; satu terbuka sekaligus. Warna hanya lewat token tema --dl-*. Pencarian dan saringan Nonaktif lewat JS kecil.
 Sumber hulu  : FacAssetController@index, FacPlaceController@index.
 Input        : groups (list: label, icon, count_text, empty, items[url, title, code, sub, off, pills[[teks, nada ok|warn|bad]]]), newUrl, searchHint.
 Output       : kartu; klik kartu membuka form.
 Dipakai oleh : fac-assets.index, fac-places.index.
 Status       : Aktif.
--}}
<style>
    .fc { max-width: 1040px }
    .fc-bar { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 14px }
    .fc-bar input[type=search] { flex: 1 1 220px; max-width: 360px; padding: 8px 12px; border: 1px solid var(--dl2-border); border-radius: 10px; background: var(--dl2-surface); color: var(--dl2-ink); font: inherit }
    .fc-off { border: 1px solid var(--dl2-border); background: var(--dl2-surface); color: var(--dl2-ink); border-radius: 10px; padding: 7px 12px; font: inherit; font-size: .85rem; cursor: pointer }
    .fc-off[aria-pressed=true] { background: var(--ak-accent-soft); color: var(--dl2-ink); border-color: var(--dl2-primary) }
    .fc-new { margin-left: auto }
    .fc-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px }
    .fc-cat { background: var(--ak-accent-soft); color: var(--dl2-ink); border: 1px solid var(--dl2-border); border-radius: 16px; animation: fc-in .35s; transition: opacity .2s }
    .fc-cat > summary { list-style: none; cursor: pointer; display: flex; flex-direction: column; align-items: center; gap: 4px; padding: 18px 8px; text-align: center }
    .fc-cat > summary::-webkit-details-marker { display: none }
    .fc-ico { font-size: 2rem; line-height: 1; color: var(--dl2-primary-ink, var(--dl2-ink)) }
    .fc-cat b { font-size: 1rem }
    .fc-cat small { color: var(--dl2-ink) }
    .fc-cat[open] { grid-column: 1 / -1 }
    .fc-cat[open] > summary { flex-direction: row; gap: 10px; padding: 8px 14px; text-align: left }
    .fc-cat[open] .fc-ico { font-size: 34px; width: 34px; text-align: center }
    .fc-cat[open] > summary::after { content: '▲'; margin-left: auto; font-size: .8rem }
    .fc-grid:has(details[open]) .fc-cat:not([open]) { opacity: .6 }
    .fc-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(210px, 1fr)); gap: 10px; padding: 4px 12px 14px; animation: fc-in .3s }
    .fc-card { display: flex; flex-direction: column; gap: 4px; padding: 10px 12px; background: var(--dl2-surface); color: var(--dl2-ink); border: 1px solid var(--dl2-border); border-radius: 12px; text-decoration: none; transition: box-shadow .15s, transform .15s }
    .fc-card:hover { box-shadow: 0 2px 10px rgba(0, 0, 0, .1); transform: translateY(-1px) }
    .fc-card b { font-size: .95rem; color: var(--dl2-ink) }
    .fc-card code { font-size: .75rem; color: var(--dl2-ink-soft) }
    .fc-pills { display: flex; gap: 6px; flex-wrap: wrap }
    .fc-pill { font-size: .75rem; padding: 1px 9px; border-radius: 99px; border: 1px solid var(--dl2-border); color: var(--dl2-ink); background: var(--dl2-surface) }
    .fc-pill--ok { border-color: var(--dl2-success) }
    .fc-pill--warn { border-color: var(--dl2-warning) }
    .fc-pill--bad { border-color: var(--dl2-danger); background: var(--ak-danger-soft) }
    .fc-card--off { opacity: .65 }
    .fc-none { padding: 6px 14px 14px }
    @keyframes fc-in { from { opacity: 0; transform: translateY(-6px) } to { opacity: 1; transform: none } }
    @media (max-width: 900px) { .fc-grid { grid-template-columns: repeat(2, 1fr) } }
    @media (prefers-reduced-motion: reduce) { .fc-cat, .fc-cards { animation: none } }
</style>
<div class="fc">
    <div class="fc-bar">
        <input type="search" id="fc-q" placeholder="{{ $searchHint }}" aria-label="{{ $searchHint }}">
        <button type="button" class="fc-off" id="fc-off" aria-pressed="false">Nonaktif ({{ collect($groups)->flatMap(fn ($g) => $g['items'])->where('off', true)->count() }})</button>
        @if ($newUrl)<x-dl2::button class="fc-new" variant="primary" size="sm" :href="$newUrl" label="+ New Record" />@endif
    </div>
    <div class="fc-grid">
        @foreach ($groups as $g)
            <details name="k" class="fc-cat">
                <summary><i class="fa-solid {{ $g['icon'] }} fc-ico" aria-hidden="true"></i><b>{{ $g['label'] }}</b><small><span data-n>{{ count($g['items']) }}</span> {{ $g['unit'] }}</small></summary>
                @if (! $g['items'])
                    <div class="fc-none">{{ $g['empty'] }}</div>
                @else
                    <div class="fc-cards">
                        @foreach ($g['items'] as $r)
                            <a href="{{ $r['url'] }}" class="fc-card {{ $r['off'] ? 'fc-card--off' : '' }}" data-off="{{ $r['off'] ? 1 : 0 }}" data-q="{{ mb_strtolower($r['code'].' '.$r['title'].' '.($r['sub'] ?? '')) }}">
                                <b>{{ $r['title'] }}</b>
                                <code>{{ $r['code'] }}@if (! empty($r['sub'])) · {{ $r['sub'] }}@endif</code>
                                <span class="fc-pills">
                                    @foreach ($r['pills'] as [$text, $tone])<span class="fc-pill fc-pill--{{ $tone }}">{{ $text }}</span>@endforeach
                                    @if ($r['off'])<span class="fc-pill">Nonaktif</span>@endif
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
        const q = document.getElementById('fc-q'), off = document.getElementById('fc-off');
        const apply = () => {
            const t = q.value.trim().toLowerCase(), onlyOff = off.getAttribute('aria-pressed') === 'true';
            let first = null;
            document.querySelectorAll('.fc-cat').forEach(cat => {
                let n = 0;
                cat.querySelectorAll('.fc-card').forEach(c => {
                    const show = c.dataset.q.includes(t) && (!onlyOff || c.dataset.off === '1');
                    c.hidden = !show; n += show;
                });
                cat.querySelector('[data-n]').textContent = n;
                cat.hidden = (t || onlyOff) && n === 0;
                if (!cat.hidden && n && !first) first = cat;
            });
            if (t && first && !document.querySelector('.fc-cat[open]:not([hidden])')) first.open = true;
        };
        q.addEventListener('input', apply);
        off.addEventListener('click', () => { off.setAttribute('aria-pressed', off.getAttribute('aria-pressed') !== 'true'); apply(); });
    });
</script>
