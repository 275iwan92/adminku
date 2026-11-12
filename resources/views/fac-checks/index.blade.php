{{-- REMARK
 Tujuan       : Daftar Opname Aset (modul FAO): tanggal, cakupan (orang atau ruang), jumlah diperiksa, jumlah tidak ditemukan, hasil (Cocok / Ada selisih).
 Sumber hulu  : FacCheckController@index (rows, findings).
 Input        : rows, findings.
 Output       : x-dl2::grid; klik 2x membuka hasil pemeriksaan.
 Dipakai oleh : route fac-checks.index (tombol Opname di panel Perawatan Gedung dan Aset).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Opname Aset'])
@section('body')
    @php $u = auth()->user(); @endphp
    @include('partials.flash')
    @if ($u->canAccess('FAM'))
        <x-dl2::action-bar align="start">
            <x-dl2::button class="sstock-btn-slate" variant="secondary" icon="fa-arrow-left" label="Kembali" :href="route('fac-care.index')" />
        </x-dl2::action-bar>
    @endif
    @if ($findings)
        <x-dl2::alert tone="warn" :message="raw($findings.' aset belum ditemukan pada pemeriksaan terakhirnya. Tindak lanjuti lewat Serah Terima (pengembalian atau laporan hilang).')" />
    @endif
    <x-dl2::grid id="fac-checks" :rows="$rows" actions="open" :open-url="url('fac-checks/{id}')" :new-url="$u->canWrite('FAO') ? route('fac-checks.create') : null" search="Cari orang atau ruang..."
        :phone-cards="['title' => 'scope', 'sub' => 'date', 'badge' => 'result', 'tones' => ['Cocok' => 'success', 'Ada selisih' => 'danger'], 'meta' => ['n', 'missing']]"
        :columns="[
            ['key' => 'date', 'label' => 'Tanggal', 'type' => 'date'],
            ['key' => 'scope', 'label' => 'Cakupan'],
            ['key' => 'n', 'label' => 'Diperiksa', 'type' => 'integer'],
            ['key' => 'missing', 'label' => 'Tidak ditemukan', 'type' => 'integer'],
            ['key' => 'result', 'label' => 'Hasil', 'type' => 'badge', 'tones' => ['Cocok' => 'success', 'Ada selisih' => 'danger']],
            ['key' => 'by', 'label' => 'Pemeriksa'],
        ]"
        :stats="[['label' => 'Semua opname'], ['label' => 'Ada selisih', 'where' => ['result' => 'Ada selisih'], 'tone' => 'danger']]" />
@endsection
