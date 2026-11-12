{{-- REMARK
 Tujuan       : Daftar Perintah Kerja / tiket perawatan (GA Gedung dan Aset, modul FAT): nomor WO, judul, aset atau gedung/ruang, prioritas dan status berlencana, penanggung pekerjaan, biaya.
 Sumber hulu  : FacOrderController@index.
 Input        : rows.
 Output       : x-dl2::grid dengan kotak status; klik 2x membuka form Perintah Kerja.
 Dipakai oleh : route fac-orders.index (tombol Tiket di panel Perawatan Gedung dan Aset).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Tiket Perawatan'])
@section('body')
    @php
        use App\Services\FacCare;
        $u = auth()->user();
    @endphp
    @include('partials.flash')
    @if ($u->canAccess('FAM'))
        <x-dl2::action-bar align="start">
            <x-dl2::button class="sstock-btn-slate" variant="secondary" icon="fa-arrow-left" label="Kembali" :href="route('fac-care.index')" />
        </x-dl2::action-bar>
    @endif
    <x-dl2::grid id="fac-orders" :rows="$rows" actions="open" :open-url="url('fac-orders/{id}')" :new-url="$u->canWrite('FAT') ? route('fac-orders.create') : null" search="Cari nomor, judul, aset, vendor..."
        :phone-cards="['title' => 'title', 'sub' => 'no', 'badge' => 'status', 'tones' => FacCare::STATUS_TONES, 'meta' => ['target', 'priority']]"
        :columns="[
            ['key' => 'no', 'label' => 'No. WO', 'type' => 'identity', 'avatar' => false],
            ['key' => 'title', 'label' => 'Judul'],
            ['key' => 'target', 'label' => 'Aset / lokasi'],
            ['key' => 'priority', 'label' => 'Prioritas', 'type' => 'badge', 'tones' => FacCare::PRIORITY_TONES],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => FacCare::STATUS_TONES],
            ['key' => 'handler', 'label' => 'Dikerjakan oleh'],
            ['key' => 'date', 'label' => 'Tanggal lapor', 'type' => 'date'],
            ['key' => 'total', 'label' => 'Biaya', 'type' => 'money'],
        ]"
        :filters="['status' => 'Status', 'priority' => 'Prioritas']"
        :stats="[['label' => 'Semua tiket'], ['label' => 'Draf', 'where' => ['status' => 'Draf']], ['label' => 'Diajukan', 'where' => ['status' => 'Diajukan'], 'tone' => 'warn'], ['label' => 'Selesai', 'where' => ['status' => 'Selesai'], 'tone' => 'success']]" />
@endsection
