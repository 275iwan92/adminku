{{-- REMARK
 Tujuan       : Rekrutmen (HRR): papan kanban kandidat per tahap (pindah bebas, loncat atau mundur) dan daftar kandidat (cari NIK / nama, filter tahap, status, sumber, bagian lewat pencarian). Satu tombol Setup (urutan tahap) dan tombol + Kandidat.
 Sumber hulu  : CandidateController@index.
 Input        : view (board | list), rows, cards, columns, steps.
 Output       : x-dl::kanban (pindah lewat POST candidates.move) atau x-dl::grid; klik 2x kandidat membuka kartu kandidat.
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
        <x-dl::kanban :columns="$columns" :cards="$cards" :add-column="false" :add-card="false" :move-url="route('candidates.move')" :me="$u->name" />
        <p class="dl-note">Seret kartu ke tahap mana pun (boleh loncat atau mundur); tiap perpindahan tercatat. Klik 2x kartu untuk membuka kartu kandidat. Kandidat Ditolak atau Mundur hanya ada di Daftar.</p>
        <style>[data-k-prio] { display: none; }</style>
        <script>
            // Klik 2x kartu membuka kartu kandidat (bukan dialog kartu bawaan papan, yang tidak dipakai di sini).
            document.addEventListener('dblclick', (ev) => {
                const card = ev.target.closest('[data-k-card]');
                if (! card) { return; }
                ev.stopImmediatePropagation();
                location.href = @json(url('candidates')) + '/' + card.dataset.kCard;
            }, true);
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
