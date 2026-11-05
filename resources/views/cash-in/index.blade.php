{{-- REMARK
 Tujuan       : Daftar Uang Masuk (Grid Transactional): nomor, tanggal, kas tujuan, sumber dana, jumlah, terpakai, sisa, status.
 Sumber hulu  : CashInController@index.
 Input        : rows.
 Output       : x-dl::grid; klik 2x membuka Uang Masuk; tombol Setup ke master Kas/Rekening bagi yang berhak.
 Dipakai oleh : route cash-in.index (menu Kas > Uang Masuk).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Uang Masuk'])
@section('body')
    @php
        $tones = ['Draf' => 'neutral', 'Diajukan' => 'warn', 'Disetujui' => 'success', 'Ditolak' => 'danger', 'Dibatalkan' => 'danger'];
    @endphp
    @include('partials.flash')
    <x-dl::action-bar align="start">
        @if (auth()->user()->canAccess('KSR'))
            <x-dl::button class="sstock-btn-slate" variant="secondary" icon="fa-building-columns" label="Setup Kas / Rekening" :href="route('cash-accounts.index')" />
        @endif
        @if (auth()->user()->canWrite('KSM'))
            <x-dl::button class="sstock-btn-slate" variant="secondary" icon="fa-hand-holding-dollar" label="Kelola Sumber Dana" :href="route('cash-sources.index')" />
        @endif
    </x-dl::action-bar>
    <x-dl::grid id="cash-in" :rows="$rows" actions="open" :open-url="url('cash-in/{id}')"
        :new-url="auth()->user()->canWrite('KSM') ? route('cash-in.create') : null"
        search="Cari nomor, kas tujuan, sumber dana..."
        :phone-cards="['title' => 'doc_no', 'sub' => 'source_name', 'badge' => 'status', 'tones' => $tones, 'meta' => ['amount', 'remaining']]"
        :columns="[
            ['key' => 'doc_no', 'label' => 'No. Uang Masuk', 'type' => 'identity', 'code' => 'cash_account', 'avatar' => false],
            ['key' => 'doc_date', 'label' => 'Tanggal', 'type' => 'date'],
            ['key' => 'source_name', 'label' => 'Sumber Dana'],
            ['key' => 'amount', 'label' => 'Jumlah', 'type' => 'money', 'sum' => true],
            ['key' => 'used', 'label' => 'Terpakai', 'type' => 'money'],
            ['key' => 'remaining', 'label' => 'Sisa', 'type' => 'money'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => $tones],
        ]"
        :filters="['status' => 'Status', 'cash_account' => 'Kas Tujuan']"
        :stats="[['label' => 'Semua Uang Masuk'], ['label' => 'Perlu tindakan', 'where' => ['open' => true], 'tone' => 'warn']]" />
@endsection
