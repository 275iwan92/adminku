{{-- REMARK
 Tujuan       : Daftar Penyesuaian Kas (Grid Transactional): nomor, tanggal, kas, arah, jumlah bertanda, alasan, status.
 Sumber hulu  : CashAdjustmentController@index.
 Input        : rows.
 Output       : x-dl2::grid; klik 2x membuka Penyesuaian Kas.
 Dipakai oleh : route cash-adjust.index (menu Keuangan > Penyesuaian Kas).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Penyesuaian Kas'])
@section('body')
    @php
        $tones = ['Draf' => 'neutral', 'Diajukan' => 'warn', 'Disetujui' => 'success', 'Ditolak' => 'danger', 'Dibatalkan' => 'danger'];
    @endphp
    @include('partials.flash')
    <x-dl2::grid id="cash-adjust" :rows="$rows" actions="open" :open-url="url('cash-adjust/{id}')"
        :new-url="auth()->user()->canWrite('KSA') ? route('cash-adjust.create') : null"
        search="Cari nomor, kas, alasan..."
        :phone-cards="['title' => 'doc_no', 'sub' => 'cause', 'badge' => 'status', 'tones' => $tones, 'meta' => ['cash_account', 'amount']]"
        :columns="[
            ['key' => 'doc_no', 'label' => 'No. Penyesuaian', 'type' => 'identity', 'code' => 'cash_account', 'avatar' => false],
            ['key' => 'doc_date', 'label' => 'Tanggal', 'type' => 'date'],
            ['key' => 'direction', 'label' => 'Arah'],
            ['key' => 'amount', 'label' => 'Jumlah', 'type' => 'money', 'sum' => true],
            ['key' => 'cause', 'label' => 'Alasan'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => $tones],
        ]"
        :filters="['status' => 'Status', 'cash_account' => 'Kas']"
        :stats="[['label' => 'Semua Penyesuaian'], ['label' => 'Perlu tindakan', 'where' => ['open' => true], 'tone' => 'warn']]" />
@endsection
