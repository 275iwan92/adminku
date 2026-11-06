{{-- REMARK
 Tujuan       : Daftar Serah Terima Aset (modul FAH): nomor ST, jenis (pinjam, bawa pulang, pengembalian, hilang), aset, pemegang, tanggal, tanggal kembali, status berlencana.
 Sumber hulu  : FacHandoverController@index.
 Input        : rows.
 Output       : x-dl::grid dengan kotak status; klik 2x membuka dokumen.
 Dipakai oleh : route fac-handovers.index (tombol Serah Terima di panel Perawatan Gedung dan Aset).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Serah Terima Aset'])
@section('body')
    @php
        use App\Services\FacCustody;
        $u = auth()->user();
    @endphp
    @include('partials.flash')
    @if ($u->canAccess('FAM'))
        <x-dl::action-bar align="start">
            <x-dl::button class="sstock-btn-slate" variant="secondary" icon="fa-arrow-left" label="Kembali" :href="route('fac-care.index')" />
        </x-dl::action-bar>
    @endif
    <x-dl::grid id="fac-handovers" :rows="$rows" actions="open" :open-url="url('fac-handovers/{id}')" :new-url="$u->canWrite('FAH') ? route('fac-handovers.create') : null" search="Cari nomor, aset, pemegang..."
        :phone-cards="['title' => 'asset', 'sub' => 'no', 'badge' => 'status', 'tones' => FacCustody::STATUS_TONES, 'meta' => ['type', 'holder']]"
        :columns="[
            ['key' => 'no', 'label' => 'No. ST', 'type' => 'identity', 'avatar' => false],
            ['key' => 'type', 'label' => 'Jenis'],
            ['key' => 'asset', 'label' => 'Aset'],
            ['key' => 'holder', 'label' => 'Pemegang'],
            ['key' => 'date', 'label' => 'Tanggal', 'type' => 'date'],
            ['key' => 'back', 'label' => 'Kembali s.d.', 'type' => 'date'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => FacCustody::STATUS_TONES],
        ]"
        :filters="['type' => 'Jenis', 'status' => 'Status']"
        :stats="[['label' => 'Semua dokumen'], ['label' => 'Draf', 'where' => ['status' => 'Draf']], ['label' => 'Diajukan', 'where' => ['status' => 'Diajukan'], 'tone' => 'warn'], ['label' => 'Disetujui', 'where' => ['status' => 'Disetujui'], 'tone' => 'success']]" />
@endsection
