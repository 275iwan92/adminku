{{-- REMARK
 Tujuan       : Daftar Jadwal perawatan berkala (modul FAJ): judul, aset atau gedung/ruang, periode, terakhir dikerjakan, jatuh tempo berikutnya (+ sisa hari), status Aman / Segera / Terlambat. Diurut dari yang paling mendesak.
 Sumber hulu  : FacPlanController@index.
 Input        : rows.
 Output       : x-dl2::grid dengan kotak status; klik 2x membuka form jadwal.
 Dipakai oleh : route fac-plans.index (tombol Jadwal di panel Perawatan Gedung dan Aset).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Jadwal Perawatan'])
@section('body')
    @php
        use App\Services\FacCare;
        $u = auth()->user();
        $tones = FacCare::DUE_TONES + ['Nonaktif' => 'neutral'];
    @endphp
    @include('partials.flash')
    @if ($u->canAccess('FAM'))
        <x-dl2::action-bar align="start">
            <x-dl2::button class="sstock-btn-slate" variant="secondary" icon="fa-arrow-left" label="Kembali" :href="route('fac-care.index')" />
        </x-dl2::action-bar>
    @endif
    <x-dl2::grid id="fac-plans" :rows="$rows" actions="edit" :open-url="url('fac-plans/{id}')" :new-url="$u->canWrite('FAJ') ? route('fac-plans.create') : null" :toggle-url="url('fac-plans/{id}/toggle')" search="Cari jadwal, aset, gedung, vendor..."
        :phone-cards="['title' => 'title', 'sub' => 'target', 'badge' => 'status', 'tones' => $tones, 'meta' => ['period', 'next']]"
        :columns="[
            ['key' => 'title', 'label' => 'Jadwal', 'type' => 'identity', 'avatar' => false],
            ['key' => 'target', 'label' => 'Aset / lokasi'],
            ['key' => 'period', 'label' => 'Periode'],
            ['key' => 'last', 'label' => 'Terakhir dikerjakan', 'type' => 'date'],
            ['key' => 'next', 'label' => 'Berikutnya'],
            ['key' => 'vendor', 'label' => 'Pelaksana'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => $tones],
        ]"
        :filters="['period' => 'Periode', 'status' => 'Status']"
        :stats="[['label' => 'Semua jadwal'], ['label' => 'Terlambat', 'where' => ['status' => 'Terlambat'], 'tone' => 'danger'], ['label' => 'Segera', 'where' => ['status' => 'Segera'], 'tone' => 'warn']]" />
@endsection
