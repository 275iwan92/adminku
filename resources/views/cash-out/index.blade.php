{{-- REMARK
 Tujuan       : Daftar Uang Keluar (Grid Transactional): nomor, tanggal, ditaut ke Uang Masuk, kas asal, jumlah, untuk apa, status.
 Sumber hulu  : CashOutController@index.
 Input        : rows.
 Output       : x-dl::grid; klik 2x membuka Uang Keluar.
 Dipakai oleh : route cash-out.index (menu Kas > Uang Keluar).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Uang Keluar'])
@section('body')
    @php
        $tones = ['Draf' => 'neutral', 'Diajukan' => 'warn', 'Disetujui' => 'success', 'Ditolak' => 'danger', 'Dibatalkan' => 'danger'];
    @endphp
    @include('partials.flash')
    @if (auth()->user()->canWrite('KSK'))
        <x-dl::action-bar align="start">
            <x-dl::button class="sstock-btn-slate" variant="secondary" icon="fa-bullseye" label="Kelola Kebutuhan" :href="route('cash-purposes.index')" />
        </x-dl::action-bar>
    @endif
    <x-dl::grid id="cash-out" :rows="$rows" actions="open" :open-url="url('cash-out/{id}')"
        :new-url="auth()->user()->canWrite('KSK') ? route('cash-out.create') : null"
        search="Cari nomor, ditaut ke, untuk apa..."
        :phone-cards="['title' => 'doc_no', 'sub' => 'purpose', 'badge' => 'status', 'tones' => $tones, 'meta' => ['cash_in', 'amount']]"
        :columns="[
            ['key' => 'doc_no', 'label' => 'No. Uang Keluar', 'type' => 'identity', 'code' => 'cash_account', 'avatar' => false],
            ['key' => 'doc_date', 'label' => 'Tanggal', 'type' => 'date'],
            ['key' => 'cash_in', 'label' => 'Ditaut Ke'],
            ['key' => 'amount', 'label' => 'Jumlah', 'type' => 'money', 'sum' => true],
            ['key' => 'purpose', 'label' => 'Untuk Apa'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => $tones],
        ]"
        :filters="['status' => 'Status']"
        :stats="[['label' => 'Semua Uang Keluar'], ['label' => 'Perlu tindakan', 'where' => ['open' => true], 'tone' => 'warn']]" />
@endsection
