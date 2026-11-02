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
    <x-dl::action-bar align="start">
        @if ($u->canWrite('HRR'))
            <x-dl::button class="sstock-btn-teal" variant="primary" icon="fa-plus" label="Kandidat" :href="route('candidates.create')" />
            <x-dl::button class="sstock-btn-slate" variant="secondary" icon="fa-gear" label="Setup" :href="route('recruit-steps.index')" />
        @endif
        <x-dl::button :variant="$view === 'board' ? 'primary' : 'secondary'" icon="fa-table-columns" label="Papan" :href="route('candidates.index')" />
        <x-dl::button :variant="$view === 'list' ? 'primary' : 'secondary'" icon="fa-list" label="Daftar" :href="route('candidates.index', ['view' => 'list'])" />
    </x-dl::action-bar>
    @if ($view === 'board')
        <x-dl::kanban :columns="$columns" :cards="$cards" :add-column="false" :add-card="false" :move-url="route('candidates.move')" :me="$u->name" open-on="click" undo />
        <p class="dl-note">Seret kartu ke tahap mana pun (boleh loncat atau mundur; di HP tahan kartu sebentar lalu seret); tiap perpindahan tercatat dan bisa diurungkan 6 detik. Ketuk kartu untuk membuka kartu kandidat. Kandidat Ditolak atau Mundur hanya ada di Daftar.</p>
        <style>
            [data-k-prio] { display: none; }
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
