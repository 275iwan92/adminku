{{-- REMARK
 Tujuan       : Rekrutmen (HRR): papan kanban kandidat per tahap (pindah bebas, loncat atau mundur) dan daftar kandidat (cari NIK / nama, filter tahap, status, sumber, bagian lewat pencarian). Satu tombol Setup (urutan tahap) dan tombol + Kandidat.
 Sumber hulu  : CandidateController@index.
 Input        : view (board | list), rows, cards, columns, steps.
 Output       : x-dl::kanban (pindah lewat POST candidates.move) atau x-dl::grid; klik kandidat membuka kartu kandidat.
 Dipakai oleh : route candidates.index (menu HR > Rekrutmen).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Rekrutmen'])
@section('body')
    @php $u = auth()->user(); @endphp
    @include('partials.flash')
    <x-dl::kpi-row>
        <x-dl::stat-box label="Kandidat aktif" :value="(string) $kpi['active']" tone="accent" />
        <x-dl::stat-box label="Interview minggu ini" :value="(string) $kpi['interviews']" tone="violet" sub="Jadwal tes dan interview yang menunggu" />
        <x-dl::stat-box label="Menunggu keputusan" :value="(string) $kpi['waiting']" tone="danger" sub="Hasil tahap berjalan belum diisi" />
        <x-dl::stat-box label="Diterima bulan ini" :value="(string) $kpi['hired']" tone="success" />
    </x-dl::kpi-row>
    <div class="rk-top">
        @if ($u->canWrite('HRR'))
            <x-dl::button class="sstock-btn-teal" variant="primary" icon="fa-plus" label="Kandidat" :href="route('candidates.create')" />
            <x-dl::button class="sstock-btn-slate" variant="secondary" icon="fa-gear" label="Setup" :href="route('recruit-steps.index')" />
        @endif
        @if ($view === 'board')
            @if (count($departments) > 1)
                <div class="rk-seg dl-segment" id="rk-seg"><button type="button" class="dl-segment__opt is-active" data-q="">Semua</button>@foreach ($departments as $d)<button type="button" class="dl-segment__opt" data-q="{{ $d }}">{{ $d }}</button>@endforeach</div>
            @endif
            <select class="rk-sel" id="rk-src" aria-label="Sumber"><option value="">Semua sumber</option>@foreach ($sources as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
            <select class="rk-sel" id="rk-who" aria-label="Penilai"><option value="">Semua penilai</option>@foreach ($assessors as $a)<option>{{ $a }}</option>@endforeach</select>
        @endif
        <span class="rk-spacer"></span>
        <div class="dl-segment">
            <a class="dl-segment__opt {{ $view === 'board' ? 'is-active' : '' }}" href="{{ route('candidates.index') }}"><i class="fa-solid fa-table-columns"></i> Papan</a>
            <a class="dl-segment__opt {{ $view === 'list' ? 'is-active' : '' }}" href="{{ route('candidates.index', ['view' => 'list']) }}"><i class="fa-solid fa-list"></i> Daftar</a>
        </div>
    </div>
    @if ($view === 'board')
        <div class="rk-legend">Sumber lamaran: @foreach (['referral' => '#639922', 'online' => '#378ADD', 'walkin' => '#EF9F27', 'other' => '#888780'] as $k => $col)<span><i style="background:{{ $col }}"></i>{{ $sources[$k] }}</span>@endforeach</div>
        <x-dl::kanban :columns="$columns" :cards="$cards" :add-column="false" :add-card="true" :members="$assessors" :move-url="route('candidates.move')" :me="$u->name" open-on="click" undo />
        <p class="dl-note">Seret kartu ke tahap mana pun (boleh loncat atau mundur; di HP tahan kartu sebentar lalu seret); tiap perpindahan tercatat dan bisa diurungkan 6 detik. Ketuk kartu untuk membuka kartu kandidat. Kandidat Ditolak atau Mundur hanya ada di Daftar.</p>
        <style>
            /* Tambah kartu hanya di kolom Daftar (kolom pertama); membuka form Kandidat Baru. */
            .dl-kanban .dl-kcol:not(:first-child) .dl-kcol__foot { display: none; }
            .rk-top { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin: 10px 0 8px; }
            .rk-spacer { flex: 1; }
            .rk-seg { display: inline-flex; flex-wrap: wrap; }
            .rk-sel { padding: 6px 8px; border: 1px solid var(--dl-border, #ccc); border-radius: 6px; background: var(--dl-surface, #fff); color: var(--dl-ink, #222); font: inherit; font-size: 12.5px; }
            .rk-legend { display: flex; flex-wrap: wrap; gap: 12px; font-size: 12px; color: var(--dl-muted, #666); margin: 0 0 8px; }
            .rk-legend i { display: inline-block; width: 18px; height: 6px; border-radius: 3px; margin-right: 5px; vertical-align: middle; }
            .rk-strip { height: 6px; margin: -10px -10px 2px; border-radius: 8px 8px 0 0; }
            .rk-att { color: var(--dl-muted, #777); font-size: 11px; }
            .rk-colmenu { border: none; background: none; cursor: pointer; color: var(--dl-muted, #777); padding: 0 4px; font-size: 15px; line-height: 1; }
            .rk-menu { position: absolute; z-index: 9998; background: var(--dl-surface, #fff); border: 1px solid var(--dl-border, #ddd); border-radius: 8px; padding: 4px; min-width: 170px; box-shadow: 0 4px 14px rgba(0,0,0,.15); }
            .rk-menu button { display: block; width: 100%; text-align: left; border: none; background: none; padding: 6px 10px; border-radius: 6px; cursor: pointer; font: inherit; font-size: 12.5px; color: inherit; }
            .rk-menu button:hover { background: var(--dl-hover, #f1f1f1); }
            /* Kolom membagi lebar papan sama rata (tanpa gulir mendatar); baru menggulir bila kolom terlalu sempit (tahap sangat banyak, layar kecil). */
            .dl-kanban .dl-kcol { flex: 1 1 0; min-width: 150px; }
            .dl-kcard { background: var(--dl-surface, #fff); }
            .dl-kcard--ghost { transform: rotate(3deg); outline: 2px solid #7F77DD; box-shadow: 0 10px 24px rgba(60, 52, 137, .28); }
            .dl-kcard__ph { background: var(--dl-surface, #fff); border-color: #7F77DD; }
            .dl-kcard__due.is-soon { color: var(--dl-danger, #c0392b); font-weight: 600; }
            .rk-ov { position: fixed; inset: 0; background: rgba(0,0,0,.45); display: flex; align-items: center; justify-content: center; z-index: 9999; padding: 12px; }
            .rk-box { background: var(--dl-surface, #fff); color: var(--dl-ink, #222); border: 1px solid var(--dl-border, #ddd); border-radius: 12px; padding: 16px 18px; width: 100%; max-width: 380px; }
            .rk-box h4 { margin: 0 0 2px; font-size: 15px; }
            .rk-sub { font-size: 12px; opacity: .75; margin-bottom: 10px; }
            .rk-row { display: flex; gap: 8px; }
            .rk-row > label { flex: 1; }
            .rk-box label { display: block; font-size: 12px; margin-bottom: 8px; }
            .rk-box input, .rk-box select { width: 100%; box-sizing: border-box; padding: 6px 8px; border: 1px solid var(--dl-border, #ccc); border-radius: 6px; background: transparent; color: inherit; font: inherit; }
            .rk-err { color: var(--dl-danger, #c0392b); font-size: 11.5px; min-height: 14px; }
            .rk-act { display: flex; gap: 8px; justify-content: flex-end; margin-top: 10px; }
        </style>
        <script>
            (() => {
                const SCHEDULED = @json($scheduled);   // id tahap => nama, tahap yang meminta tanggal + jam
                const ASSESSORS = @json($assessors);
                const board = document.querySelector('[data-dl-kanban]');

                // "Tambah kartu" di kolom Daftar = form Kandidat Baru (bukan kartu cepat bawaan papan).
                board.addEventListener('click', (ev) => {
                    if (! ev.target.closest('[data-k-add]')) { return; }
                    ev.preventDefault(); ev.stopImmediatePropagation();
                    location.href = @json(route('candidates.create'));
                }, true);

                // Segmen bagian di atas papan: mengisi kotak cari papan (kartu memuat nama bagiannya).
                document.getElementById('rk-seg')?.addEventListener('click', (ev) => {
                    const b = ev.target.closest('[data-q]'); if (! b) { return; }
                    document.querySelectorAll('#rk-seg [data-q]').forEach((x) => x.classList.toggle('is-active', x === b));
                    const box = board.querySelector('[data-k-search]'); box.value = b.dataset.q; box.dispatchEvent(new Event('input', { bubbles: true }));
                });

                // Ketuk atau klik kartu membuka kartu kandidat (bukan dialog kartu bawaan papan, yang tidak dipakai di sini).
                board.addEventListener('dl:kanban-open', (ev) => { ev.preventDefault(); location.href = @json(url('candidates')) + '/' + ev.detail.card.id; });

                // Pindah ke tahap berjadwal ditahan dulu (kartu belum pindah) sampai tanggal dan jam diisi; Batal = tetap di tahap asal.
                board.addEventListener('dl:kanban-beforemove', (ev) => {
                    const { card, to, resume } = ev.detail;
                    if (! (to in SCHEDULED)) { return; }
                    ev.preventDefault();
                    ask(card, to, resume);
                });

                function ask(card, to, resume) {
                    const opts = '<option value="">Pilih penilai</option>' + ASSESSORS.map((a) => `<option>${a.replace(/</g, '&lt;')}</option>`).join('');
                    const ov = document.createElement('div');
                    ov.className = 'rk-ov';
                    ov.innerHTML = `<div class="rk-box" role="dialog" aria-modal="true"><h4>Jadwalkan: ${SCHEDULED[to]}</h4><div class="rk-sub">${card.title.replace(/</g, '&lt;')}</div>
                        <div class="rk-row"><label>Tanggal (wajib)<input type="date" data-f="date"><span class="rk-err" data-e="date"></span></label><label>Jam (wajib)<input type="time" data-f="time"><span class="rk-err" data-e="time"></span></label></div>
                        <label>Penilai<select data-f="assessor">${opts}</select></label><label>Catatan<input type="text" maxlength="255" data-f="note"></label>
                        <div class="rk-act"><button type="button" class="dl-btn sstock-btn-slate" data-x="cancel">Batal</button><button type="button" class="dl-btn sstock-btn-teal" data-x="save">Simpan</button></div></div>`;
                    document.body.appendChild(ov);
                    const q = (s) => ov.querySelector(s);
                    const close = () => { ov.remove(); document.removeEventListener('keydown', onKey); };
                    const onKey = (e) => { if (e.key === 'Escape') { close(); } };
                    document.addEventListener('keydown', onKey);
                    ov.addEventListener('click', (e) => { if (e.target === ov || e.target.closest('[data-x="cancel"]')) { close(); } });
                    q('[data-f="date"]').focus();
                    q('[data-x="save"]').addEventListener('click', () => {
                        const v = (f) => q(`[data-f="${f}"]`).value;
                        q('[data-e="date"]').textContent = v('date') ? '' : 'Isi tanggal.';
                        q('[data-e="time"]').textContent = v('time') ? '' : 'Isi jam.';
                        if (! v('date') || ! v('time')) { return; }
                        card.due = v('date') + ' ' + v('time');   // kartu langsung menampilkan jadwal barunya
                        close();
                        resume({ scheduled_on: v('date'), scheduled_time: v('time'), assessor: v('assessor') || null, note: v('note') || null });
                    });
                }

                // Tampilan kartu: strip warna sumber, ikon lampiran, jadwal merah bila dalam 24 jam, filter Sumber dan Penilai, menu kolom.
                const META = @json($meta);
                const COLORS = { referral: '#639922', online: '#378ADD', walkin: '#EF9F27', other: '#888780' };
                const flt = { src: '', who: '' };
                const cardData = (id) => board.dlKanban.cards.find((c) => String(c.id) === String(id));
                function decorate() {
                    board.querySelectorAll('.dl-kcard[data-k-card]').forEach((el) => {
                        const id = el.dataset.kCard, m = META[id];
                        if (! m) { return; }
                        if (! el.querySelector('.rk-strip')) { const s = document.createElement('div'); s.className = 'rk-strip'; s.style.background = COLORS[m.src] || COLORS.other; el.prepend(s); }
                        if (m.att && ! el.querySelector('.rk-att')) { el.querySelector('.dl-kcard__spacer')?.insertAdjacentHTML('beforebegin', '<span class="rk-att" title="Ada lampiran"><i class="fa-solid fa-paperclip"></i></span>'); }
                        const show = (! flt.src || m.src === flt.src) && (! flt.who || m.assessor === flt.who);
                        if (el.hidden === show) { el.hidden = ! show; }
                        const d = cardData(id)?.due, chip = el.querySelector('.dl-kcard__due');
                        if (chip && d) { const ms = new Date(d.replace(' ', 'T')) - Date.now(); chip.classList.toggle('is-soon', ms >= 0 && ms <= 864e5); }
                    });
                    board.querySelectorAll('.dl-kcol__head').forEach((h) => {
                        if (h.querySelector('.rk-colmenu')) { return; }
                        const b = document.createElement('button'); b.type = 'button'; b.className = 'rk-colmenu'; b.title = 'Menu kolom'; b.textContent = '\u22EF'; h.append(b);
                    });
                }
                new MutationObserver(decorate).observe(board.querySelector('[data-k-board]'), { childList: true, subtree: true });
                decorate();
                document.getElementById('rk-src')?.addEventListener('change', (e) => { flt.src = e.target.value; decorate(); });
                document.getElementById('rk-who')?.addEventListener('change', (e) => { flt.who = e.target.value; decorate(); });

                // Menu kolom: urutkan kartu di kolom itu (hanya tampilan; urutan kembali normal setelah papan digambar ulang).
                board.addEventListener('click', (ev) => {
                    const mb = ev.target.closest('.rk-colmenu');
                    if (! mb) { return; }
                    ev.stopPropagation();
                    document.querySelectorAll('.rk-menu').forEach((x) => x.remove());
                    const body = mb.closest('[data-k-col]').querySelector('[data-k-drop]');
                    const sorts = { 'Urutkan: jadwal terdekat': (a, b) => (cardData(a.dataset.kCard)?.due || '9').localeCompare(cardData(b.dataset.kCard)?.due || '9'),
                        'Urutkan: nama A-Z': (a, b) => cardData(a.dataset.kCard).title.localeCompare(cardData(b.dataset.kCard).title),
                        'Urutkan: terbaru dilamar': (a, b) => META[b.dataset.kCard].applied.localeCompare(META[a.dataset.kCard].applied) };
                    const m = document.createElement('div'); m.className = 'rk-menu';
                    Object.keys(sorts).forEach((l) => { const o = document.createElement('button'); o.type = 'button'; o.textContent = l; o.onclick = () => { [...body.querySelectorAll('.dl-kcard[data-k-card]')].sort(sorts[l]).forEach((c) => body.append(c)); m.remove(); }; m.append(o); });
                    const r = mb.getBoundingClientRect(); m.style.left = Math.min(r.left + scrollX, innerWidth - 190) + 'px'; m.style.top = (r.bottom + scrollY + 4) + 'px';
                    document.body.append(m); setTimeout(() => document.addEventListener('click', () => m.remove(), { once: true }));
                });

                // Papan terbuka (tab ini tampak) = panel kiri shell mengecil otomatis jadi lajur ikon; kembali normal saat pindah tab atau ditutup.
                // Hanya otomatis: bila pemakai mengubah sendiri (tombol panel) selama papan terbuka, pilihannya dibiarkan dan tidak dikembalikan; pilihan tersimpan tidak ditimpa.
                (() => {
                    let host, lm, btn, key, saved, applied = false, busy = false;
                    try { host = window.parent !== window ? window.parent : null; lm = host?.document.querySelector('[data-dl-layer-menu]'); btn = lm?.querySelector('[data-lm-mini]'); } catch (e) { return; }
                    if (! lm || ! btn) { return; }
                    key = 'dl-lm-' + lm.dataset.id + '-mini';
                    const store = (v) => { try { v === null ? host.localStorage.removeItem(key) : host.localStorage.setItem(key, v); } catch (e) { /* tanpa penyimpanan: abaikan */ } };
                    lm.addEventListener('dl:layer-menu-mini', () => { if (! busy) { applied = false; } });   // perubahan manual: jangan dikembalikan
                    const press = () => { busy = true; btn.click(); busy = false; };
                    const visible = () => window.frameElement ? window.frameElement.getClientRects().length > 0 : true;
                    const sync = () => {
                        if (visible() && ! applied && ! lm.classList.contains('is-mini')) {
                            try { saved = host.localStorage.getItem(key); } catch (e) { saved = null; }
                            press(); store(saved); applied = true;
                        } else if (! visible() && applied) {
                            if (lm.classList.contains('is-mini')) { press(); store(saved); }
                            applied = false;
                        }
                    };
                    sync();
                    setInterval(sync, 400);
                    window.addEventListener('pagehide', () => { if (applied && lm.classList.contains('is-mini')) { press(); store(saved); applied = false; } });
                })();
            })();
        </script>
    @else
        <x-dl::grid id="candidates" :rows="$rows" actions="open" :open-url="url('candidates/{id}')"
            search="Cari NIK, nama, bagian..."
            :phone-cards="['title' => 'name', 'sub' => 'nik', 'meta' => ['depts', 'step', 'result']]"
            :columns="[
                ['key' => 'name', 'label' => 'Kandidat', 'type' => 'identity', 'code' => 'nik', 'box' => true],
                ['key' => 'depts', 'label' => 'Bagian dilamar'],
                ['key' => 'step', 'label' => 'Tahap'],
                ['key' => 'result', 'label' => 'Hasil'],
                ['key' => 'schedule', 'label' => 'Jadwal berikutnya'],
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'source', 'label' => 'Sumber', 'visible' => false],
                ['key' => 'applied_at', 'label' => 'Tanggal lamar', 'type' => 'date'],
            ]"
            :filters="['step' => 'Tahap', 'status' => 'Status', 'source' => 'Sumber', 'result' => 'Hasil']"
            :stats="[['label' => 'Semua kandidat'], ['label' => 'Aktif', 'where' => ['active' => true]], ['label' => 'Ditolak / mundur', 'where' => ['active' => false], 'tone' => 'warn']]" />
    @endif
@endsection
