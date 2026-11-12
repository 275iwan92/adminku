{{-- REMARK
 Tujuan       : Daftar Kunjungan AC (Grid Transactional polos, modul GAV): nomor pesanan tebal di kolom pertama, tanggal, jam, vendor, jumlah AC, dan kolom Status berlencana (Draf, Terkirim, Dikonfirmasi vendor, Selesai, Dijadwal ulang, Dibatalkan).
                Pesanan dibuat dari layar Penjadwalan AC; pemberitahuan dikirim dari layar satu pesanan.
 Sumber hulu  : AcVisitController@index.
 Input        : rows.
 Output       : x-dl2::grid dengan kotak status; klik 2x / Open membuka layar pesanan.
 Dipakai oleh : route ac-visits.index (menu GA > Kunjungan AC).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Kunjungan AC'])
@section('body')
    @php($tones = \App\Services\AcCare::ORDER_TONES)
    @include('partials.flash')
    @php($typeTones = ['Preventif' => 'neutral', 'Breakdown' => 'danger'])
    <x-dl2::action-bar align="start">
        @if (auth()->user()->canAccess('GAM'))
            <x-dl2::button class="sstock-btn-slate" variant="secondary" icon="fa-arrow-left" label="Kembali" :href="route('ac-care.index')" />
        @endif
        @if (auth()->user()->canAccess('GAS'))
            <x-dl2::button class="sstock-btn-teal" variant="secondary" icon="fa-calendar-check" label="Jadwal" :href="route('ac-schedule.index')" />
        @endif
        <x-dl2::button class="sstock-btn-coral" icon="fa-truck-fast" label="Kunjungan dan Catat hasil" :href="route('ac-visits.index')" />
    </x-dl2::action-bar>
    <x-dl2::grid id="ac-visits" :rows="$rows" actions="worklist" :new-record="null" :open-url="url('ac-visits/{id}')" search="Cari nomor, vendor, status..."
        :phone-cards="['title' => 'no', 'sub' => 'vendor', 'badge' => 'status', 'tones' => $tones, 'meta' => ['date', 'time', 'units']]"
        :columns="[
            ['key' => 'no', 'label' => 'No. Pesanan', 'type' => 'identity', 'avatar' => false],
            ['key' => 'type', 'label' => 'Tipe', 'type' => 'badge', 'tones' => $typeTones],
            ['key' => 'date', 'label' => 'Tanggal kunjungan', 'type' => 'date'],
            ['key' => 'time', 'label' => 'Jam mulai'],
            ['key' => 'vendor', 'label' => 'Vendor'],
            ['key' => 'units', 'label' => 'Jumlah AC', 'type' => 'integer'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => $tones],
            ['key' => 'sent', 'label' => 'Dikirim', 'visible' => false],
            ['key' => 'confirmed', 'label' => 'Dikonfirmasi', 'visible' => false],
            ['key' => 'done', 'label' => 'Selesai', 'visible' => false],
            ['key' => 'note', 'label' => 'Alasan jadwal ulang / batal', 'visible' => false],
        ]"
        :stats="[['label' => 'Semua'], ['label' => 'Preventif', 'where' => ['type' => 'Preventif']], ['label' => 'Breakdown', 'where' => ['type' => 'Breakdown'], 'tone' => 'danger'], ['label' => 'Draf', 'where' => ['status' => 'Draf']], ['label' => 'Terkirim', 'where' => ['status' => 'Terkirim'], 'tone' => 'info'], ['label' => 'Dikonfirmasi vendor', 'where' => ['status' => 'Dikonfirmasi vendor']], ['label' => 'Selesai', 'where' => ['status' => 'Selesai'], 'tone' => 'success'], ['label' => 'Dijadwal ulang', 'where' => ['status' => 'Dijadwal ulang'], 'tone' => 'warn']]" />
@endsection
