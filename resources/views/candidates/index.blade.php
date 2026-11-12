{{-- REMARK
 Tujuan       : Rekrutmen (HRR): papan kanban kandidat per tahap (pindah bebas, loncat atau mundur) dan daftar kandidat (cari NIK / nama, filter tahap, status, sumber, bagian lewat pencarian). Satu tombol Setup (urutan tahap) dan tombol + Kandidat.
 Sumber hulu  : CandidateController@index.
 Input        : view (board | list), rows, cards, columns, steps.
 Output       : x-dl2::kanban (pindah lewat POST candidates.move) atau x-dl2::grid; klik kandidat membuka kartu kandidat.
 Dipakai oleh : route candidates.index (menu HR > Rekrutmen).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Rekrutmen'])
@section('body')
    @php $u = auth()->user(); @endphp
    @include('partials.flash')
    <x-dl2::kpi-row>
        <x-dl2::stat-box label="Kandidat aktif" :value="(string) $kpi['active']" tone="accent" />
        <x-dl2::stat-box label="Interview minggu ini" :value="(string) $kpi['interviews']" tone="violet" sub="Jadwal tes dan interview yang menunggu" />
        <x-dl2::stat-box label="Menunggu keputusan" :value="(string) $kpi['waiting']" tone="danger" sub="Hasil tahap berjalan belum diisi" />
        <x-dl2::stat-box label="Diterima bulan ini" :value="(string) $kpi['hired']" tone="success" />
    </x-dl2::kpi-row>
    <div class="rk-top">
        @if ($u->canWrite('HRR'))
            <x-dl2::button class="sstock-btn-teal" variant="primary" icon="fa-plus" label="Kandidat" :href="route('candidates.create')" />
            <x-dl2::button class="sstock-btn-slate" variant="secondary" icon="fa-gear" label="Setup" :href="route('recruit-steps.index')" />
        @endif
        @if ($view === 'board')
            @if (count($departments) > 1)
                <div class="rk-seg ak-seg" id="rk-seg"><button type="button" class="ak-seg__opt is-active" data-q="">Semua</button>@foreach ($departments as $d)<button type="button" class="ak-seg__opt" data-q="{{ $d }}">{{ $d }}</button>@endforeach</div>
            @endif
            <select class="rk-sel" id="rk-src" aria-label="Sumber"><option value="">Semua sumber</option>@foreach ($sources as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
            <select class="rk-sel" id="rk-who" aria-label="Penilai"><option value="">Semua penilai</option>@foreach ($assessors as $a)<option>{{ $a }}</option>@endforeach</select>
        @endif
        <span class="rk-spacer"></span>
        <div class="ak-seg">
            <a class="ak-seg__opt {{ $view === 'board' ? 'is-active' : '' }}" href="{{ route('candidates.index') }}"><i class="fa-solid fa-table-columns"></i> Papan</a>
            <a class="ak-seg__opt {{ $view === 'list' ? 'is-active' : '' }}" href="{{ route('candidates.index', ['view' => 'list']) }}"><i class="fa-solid fa-list"></i> Daftar</a>
        </div>
    </div>
    @if ($view === 'board')
        <div class="rk-legend">Sumber lamaran: @foreach (['referral' => '#639922', 'online' => '#378ADD', 'walkin' => '#EF9F27', 'other' => '#888780'] as $k => $col)<span><i style="background:{{ $col }}"></i>{{ $sources[$k] }}</span>@endforeach</div>
        <x-dl2::kanban :columns="$columns" :cards="$cards" :add-column="false" :add-card="true" :members="$assessors" :me="$u->name" open-on="click" undo :config="['openUrl' => url('candidates').'/{id}']" />
        <p class="ak-note">Seret kartu ke tahap mana pun (boleh loncat atau mundur; di HP tahan kartu sebentar lalu seret); tiap perpindahan tercatat dan bisa diurungkan 6 detik. Ketuk kartu untuk membuka kartu kandidat. Kandidat Ditolak atau Mundur hanya ada di Daftar.</p>
        <style>
            /* Tambah kartu hanya di kolom Daftar (kolom pertama); membuka form Kandidat Baru. */
            .dl2-kanban .dl2-kb-col:not(:first-child) .dl2-kb-foot { display: none; }
            .dl2-kanban .dl2-kb-new, .dl2-kanban [data-kb-rm] { display: none; }
            .dl2-kanban .dl2-kb-foot { justify-content: flex-end; }
            .rk-top { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin: 10px 0 8px; }
            .rk-spacer { flex: 1; }
            .rk-seg { display: inline-flex; flex-wrap: wrap; }
            .rk-sel { padding: 6px 8px; border: 1px solid var(--dl2-border, #ccc); border-radius: 6px; background: var(--dl2-surface, #fff); color: var(--dl2-ink, #222); font: inherit; font-size: 12.5px; }
            .rk-legend { display: flex; flex-wrap: wrap; gap: 12px; font-size: 12px; color: var(--dl2-ink-soft, #666); margin: 0 0 8px; }
            .rk-legend i { display: inline-block; width: 18px; height: 6px; border-radius: 3px; margin-right: 5px; vertical-align: middle; }
            .rk-strip { position: absolute; left: 0; right: 0; top: 0; height: 6px; border-radius: 8px 8px 0 0; }
            .rk-photo { position: absolute; right: 8px; top: 14px; width: 34px; height: 34px; border-radius: 50%; object-fit: cover; border: 1px solid var(--dl2-border, #ddd); }
            .dl2-kb-card { position: relative; padding-top: 14px; }
            .dl2-kb-card:has(.rk-photo) { padding-right: 50px; }
            .rk-att { color: var(--dl2-ink-soft, #777); font-size: 11px; }
            .rk-colmenu { border: none; background: none; cursor: pointer; color: var(--dl2-ink-soft, #777); padding: 0 4px; font-size: 15px; line-height: 1; }
            .rk-menu { position: absolute; z-index: 9998; background: var(--dl2-surface, #fff); border: 1px solid var(--dl2-border, #ddd); border-radius: 8px; padding: 4px; min-width: 170px; box-shadow: 0 4px 14px rgba(0,0,0,.15); }
            .rk-menu button { display: block; width: 100%; text-align: left; border: none; background: none; padding: 6px 10px; border-radius: 6px; cursor: pointer; font: inherit; font-size: 12.5px; color: inherit; }
            .rk-menu button:hover { background: var(--dl2-hover, #f1f1f1); }
            /* Kolom membagi lebar papan sama rata (tanpa gulir mendatar); baru menggulir bila kolom terlalu sempit (tahap sangat banyak, layar kecil). */
            .dl2-kanban .dl2-kb-col { flex: 1 1 0; min-width: 150px; }
            .dl2-kb-card { background: var(--dl2-surface, #fff); }
            .dl2-kb-card.is-drag { transform: rotate(3deg); outline: 2px solid #7F77DD; box-shadow: 0 10px 24px rgba(60, 52, 137, .28); }
            .dl2-kb-body.is-over { background: var(--dl2-surface, #fff); border-color: #7F77DD; }
            .dl2-kb-card .is-soon { color: var(--dl2-danger, #c0392b); font-weight: 600; }
            .rk-ov { position: fixed; inset: 0; background: rgba(0,0,0,.45); display: flex; align-items: center; justify-content: center; z-index: 9999; padding: 12px; }
            .rk-box { background: var(--dl2-surface, #fff); color: var(--dl2-ink, #222); border: 1px solid var(--dl2-border, #ddd); border-radius: 12px; padding: 16px 18px; width: 100%; max-width: 380px; }
            .rk-box h4 { margin: 0 0 2px; font-size: 15px; }
            .rk-sub { font-size: 12px; opacity: .75; margin-bottom: 10px; }
            .rk-row { display: flex; gap: 8px; }
            .rk-row > label { flex: 1; }
            .rk-box label { display: block; font-size: 12px; margin-bottom: 8px; }
            .rk-box input, .rk-box select { width: 100%; box-sizing: border-box; padding: 6px 8px; border: 1px solid var(--dl2-border, #ccc); border-radius: 6px; background: transparent; color: inherit; font: inherit; }
            .rk-err { color: var(--dl2-danger, #c0392b); font-size: 11.5px; min-height: 14px; }
            .rk-act { display: flex; gap: 8px; justify-content: flex-end; margin-top: 10px; }
        </style>
        <script>
            window.akReady(() => {
                const SCHEDULED = @json($scheduled);   // id tahap => nama, tahap yang meminta tanggal + jam
                const ASSESSORS = @json($assessors);
                const board = document.querySelector('[data-dl2="kanban"]');
                const kb = () => Devlib2.classes.kanban.getInstance(board);

                // "Tambah kartu" di kolom Daftar = form Kandidat Baru (bukan kartu cepat bawaan papan).
                board.addEventListener('click', (ev) => {
                    if (! ev.target.closest('[data-kb-add]')) { return; }
                    ev.preventDefault(); ev.stopImmediatePropagation();
                    location.href = @json(route('candidates.create'));
                }, true);

                // Segmen bagian di atas papan: menyaring kartu yang memuat nama bagiannya.
                document.getElementById('rk-seg')?.addEventListener('click', (ev) => {
                    const b = ev.target.closest('[data-q]'); if (! b) { return; }
                    document.querySelectorAll('#rk-seg [data-q]').forEach((x) => x.classList.toggle('is-active', x === b));
                    flt.dept = b.dataset.q.toLowerCase(); decorate();
                });

                // Ketuk atau klik kartu membuka kartu kandidat (openOn=click + openUrl di konfigurasi papan).

                // Pindah kartu disimpan ke server (POST candidates.move); isian jadwal dari dialog ikut di kartu. Gagal = muat ulang agar papan kembali ke keadaan server.
                board.addEventListener('move.dl2.kanban', (ev) => {
                    const { card, to } = ev.detail, body = { card: card.id, to };
                    ['scheduled_on', 'scheduled_time', 'assessor', 'note', 'no_result'].forEach((f) => { if (card[f] != null) { body[f] = card[f]; delete card[f]; } });
                    fetch(@json(route('candidates.move')), { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' }, body: JSON.stringify(body) })
                        .then(async (r) => { if (! r.ok) { throw new Error((await r.json().catch(() => ({}))).message || 'Gagal memindahkan kartu.'); } })
                        .catch((e) => { Devlib2.toast?.(e.message, { tone: 'danger' }); setTimeout(() => location.reload(), 1200); });
                });

                // Pindah ke tahap berjadwal ditahan dulu (kartu belum pindah) sampai tanggal dan jam diisi; Batal = tetap di tahap asal.
                board.addEventListener('beforemove.dl2.kanban', (ev) => {
                    const { card, from, to, resume } = ev.detail;
                    const warn = from !== to && META[card.id]?.pending, sched = to in SCHEDULED;
                    if (! warn && ! sched) { return; }
                    ev.preventDefault();
                    const done = (extra = {}) => {
                        const go = (more = {}) => { META[card.id].pending = sched; resume({ ...extra, ...more }); };
                        sched ? ask(card, to, go) : go();
                    };
                    warn ? warnNoResult(card, done) : done();
                });

                // Keluar dari tahap berjadwal tanpa hasil: konfirmasi. Isi hasil dulu / Tetap pindahkan (tercatat "dipindah tanpa hasil") / Batal (kartu tetap di tahap asal).
                function warnNoResult(card, done) {
                    const step = META[card.id].stepName, name = card.title.replace(/</g, '&lt;');
                    const ov = document.createElement('div');
                    ov.className = 'rk-ov';
                    ov.innerHTML = `<div class="rk-box" role="alertdialog" aria-modal="true"><h4>Hasil belum diisi</h4><div class="rk-sub">Hasil ${step} untuk ${name} belum diisi. Pindahkan tanpa hasil?</div>
                        <div data-pane="ask" class="rk-act" style="flex-wrap:wrap"><button type="button" class="dl2-btn sstock-btn-slate" data-x="cancel">Batal</button><button type="button" class="dl2-btn sstock-btn-amber" data-x="skip">Tetap pindahkan</button><button type="button" class="dl2-btn sstock-btn-teal" data-x="fill">Isi hasil dulu</button></div>
                        <div data-pane="fill" hidden><label>Hasil (wajib)<select data-f="result"><option value="">Pilih hasil</option><option value="pass">Lulus</option><option value="fail">Tidak lulus</option></select><span class="rk-err" data-e="result"></span></label><label>Catatan<input type="text" maxlength="255" data-f="note"></label><div class="rk-err" data-e="all"></div>
                        <div class="rk-act"><button type="button" class="dl2-btn sstock-btn-slate" data-x="cancel">Batal</button><button type="button" class="dl2-btn sstock-btn-teal" data-x="save">Simpan dan pindahkan</button></div></div></div>`;
                    document.body.appendChild(ov);
                    const q = (s) => ov.querySelector(s), close = () => ov.remove();
                    ov.addEventListener('click', async (e) => {
                        const x = e.target.closest('[data-x]')?.dataset.x;
                        if (e.target === ov || x === 'cancel') { close(); return; }
                        if (x === 'skip') { close(); done({ no_result: 1 }); return; }
                        if (x === 'fill') { q('[data-pane="ask"]').hidden = true; q('[data-pane="fill"]').hidden = false; q('[data-f="result"]').focus(); return; }
                        if (x === 'save') {
                            const result = q('[data-f="result"]').value;
                            q('[data-e="result"]').textContent = result ? '' : 'Pilih hasil.';
                            if (! result) { return; }
                            const r = await fetch(RESULT_URL.replace('__ID__', card.id), { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': window.DL_CSRF || '' }, body: JSON.stringify({ result, note: q('[data-f="note"]').value || null }) });
                            if (! r.ok) { q('[data-e="all"]').textContent = (await r.json().catch(() => ({}))).message || 'Gagal menyimpan.'; return; }
                            META[card.id].pending = false; close(); done();
                        }
                    });
                }

                function ask(card, to, resume) {
                    const opts = '<option value="">Pilih penilai</option>' + ASSESSORS.map((a) => `<option>${a.replace(/</g, '&lt;')}</option>`).join('');
                    const ov = document.createElement('div');
                    ov.className = 'rk-ov';
                    ov.innerHTML = `<div class="rk-box" role="dialog" aria-modal="true"><h4>Jadwalkan: ${SCHEDULED[to]}</h4><div class="rk-sub">${card.title.replace(/</g, '&lt;')}</div>
                        <div class="rk-row"><label>Tanggal (wajib)<input type="date" data-f="date"><span class="rk-err" data-e="date"></span></label><label>Jam (wajib)<input type="time" data-f="time"><span class="rk-err" data-e="time"></span></label></div>
                        <label>Penilai<select data-f="assessor">${opts}</select></label><label>Catatan<input type="text" maxlength="255" data-f="note"></label>
                        <div class="rk-act"><button type="button" class="dl2-btn sstock-btn-slate" data-x="cancel">Batal</button><button type="button" class="dl2-btn sstock-btn-teal" data-x="save">Simpan</button></div></div>`;
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
                const RESULT_URL = @json(route('candidates.result', '__ID__'));
                const COLORS = { referral: '#639922', online: '#378ADD', walkin: '#EF9F27', other: '#888780' };
                const flt = { src: '', who: '', dept: '' };
                const cardData = (id) => kb().cards.find((c) => String(c.id) === String(id));
                function decorate() {
                    board.querySelectorAll('.dl2-kb-card[data-id]').forEach((el) => {
                        const id = el.dataset.id, m = META[id];
                        if (! m) { return; }
                        if (! el.querySelector('.rk-strip')) { const s = document.createElement('div'); s.className = 'rk-strip'; s.style.background = COLORS[m.src] || COLORS.other; el.prepend(s); }
                        if (m.photo && ! el.querySelector('.rk-photo')) { el.insertAdjacentHTML('afterbegin', `<img class="rk-photo" src="${m.photo}" alt="">`); }
                        if (m.att && ! el.querySelector('.rk-att')) { el.querySelector('[data-kb-rm]')?.insertAdjacentHTML('beforebegin', '<span class="rk-att" title="Ada lampiran"><i class="fa-solid fa-paperclip"></i></span>'); }
                        const show = (! flt.src || m.src === flt.src) && (! flt.who || m.assessor === flt.who) && (! flt.dept || el.textContent.toLowerCase().includes(flt.dept));
                        if (el.hidden === show) { el.hidden = ! show; }
                        const d = cardData(id)?.due, chip = [...el.querySelectorAll('.dl2-kb-meta .dl2-badge')].find((b) => b.textContent.trim() === String(d ?? ''));
                        if (chip && d) { const ms = new Date(d.replace(' ', 'T')) - Date.now(); chip.classList.toggle('is-soon', ms >= 0 && ms <= 864e5); }
                    });
                    board.querySelectorAll('.dl2-kb-head').forEach((h) => {
                        if (h.querySelector('.rk-colmenu')) { return; }
                        const b = document.createElement('button'); b.type = 'button'; b.className = 'rk-colmenu'; b.title = 'Menu kolom'; b.textContent = '\u22EF'; h.append(b);
                    });
                }
                new MutationObserver(decorate).observe(board, { childList: true, subtree: true });
                decorate();
                document.getElementById('rk-src')?.addEventListener('change', (e) => { flt.src = e.target.value; decorate(); });
                document.getElementById('rk-who')?.addEventListener('change', (e) => { flt.who = e.target.value; decorate(); });

                // Menu kolom: urutkan kartu di kolom itu (hanya tampilan; urutan kembali normal setelah papan digambar ulang).
                board.addEventListener('click', (ev) => {
                    const mb = ev.target.closest('.rk-colmenu');
                    if (! mb) { return; }
                    ev.stopPropagation();
                    document.querySelectorAll('.rk-menu').forEach((x) => x.remove());
                    const body = mb.closest('.dl2-kb-col').querySelector('.dl2-kb-body');
                    const sorts = { 'Urutkan: jadwal terdekat': (a, b) => (cardData(a.dataset.id)?.due || '9').localeCompare(cardData(b.dataset.id)?.due || '9'),
                        'Urutkan: nama A-Z': (a, b) => cardData(a.dataset.id).title.localeCompare(cardData(b.dataset.id).title),
                        'Urutkan: terbaru dilamar': (a, b) => META[b.dataset.id].applied.localeCompare(META[a.dataset.id].applied) };
                    const m = document.createElement('div'); m.className = 'rk-menu';
                    Object.keys(sorts).forEach((l) => { const o = document.createElement('button'); o.type = 'button'; o.textContent = l; o.onclick = () => { [...body.querySelectorAll('.dl2-kb-card[data-id]')].sort(sorts[l]).forEach((c) => body.append(c)); m.remove(); }; m.append(o); });
                    const r = mb.getBoundingClientRect(); m.style.left = Math.min(r.left + scrollX, innerWidth - 190) + 'px'; m.style.top = (r.bottom + scrollY + 4) + 'px';
                    document.body.append(m); setTimeout(() => document.addEventListener('click', () => m.remove(), { once: true }));
                });

                // Papan terbuka (tab ini tampak) = panel kiri shell mengecil otomatis jadi lajur ikon; kembali normal saat pindah tab atau ditutup.
                // Hanya otomatis: bila pemakai mengubah sendiri (tombol panel) selama papan terbuka, pilihannya dibiarkan dan tidak dikembalikan; pilihan tersimpan tidak ditimpa.
                (() => {
                    let host, lm, fold, applied = false, manual = false;
                    try { host = window.parent !== window ? window.parent : null; lm = host?.document.querySelector('[data-dl2="layer-menu"]'); fold = lm?.querySelector('.dl2-lm-fold'); } catch (e) { return; }
                    if (! lm || ! fold) { return; }
                    const mini = () => lm.classList.contains('is-collapsed');
                    const visible = () => window.frameElement ? window.frameElement.getClientRects().length > 0 : true;
                    const restore = () => { if (applied && mini()) { fold.click(); } applied = false; };
                    const sync = () => {
                        if (visible() && ! applied && ! manual && ! mini()) { fold.click(); applied = true; }
                        else if (applied && visible() && ! mini()) { applied = false; manual = true; }   // pemakai membuka panel sendiri: jangan dikembalikan
                        else if (! visible() && applied) { restore(); }
                        if (! visible()) { manual = false; }
                    };
                    sync();
                    setInterval(sync, 400);
                    window.addEventListener('pagehide', restore);
                })();
            });
        </script>
    @else
        <x-dl2::grid id="candidates" :rows="$rows" actions="open" :open-url="url('candidates/{id}')"
            search="Cari NIK, nama, bagian..."
            :phone-cards="['title' => 'name', 'sub' => 'nik', 'meta' => ['depts', 'step', 'result']]"
            :columns="[
                ['key' => 'name', 'label' => 'Kandidat', 'type' => 'identity', 'code' => 'nik', 'box' => false],
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
