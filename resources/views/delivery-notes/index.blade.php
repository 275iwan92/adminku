{{-- REMARK
 Tujuan       : Daftar Surat Jalan (Pengeluaran Barang): nomor, tanggal, penerima, jumlah barang, trip pemakai (nomor, plat, sopir), status. Surat jalan baru dan buka memakai form Pengeluaran Barang.
 Sumber hulu  : DeliveryNoteController@index.
 Input        : rows.
 Output       : x-dl2::grid; klik 2x membuka dokumen di Stok.
 Dipakai oleh : route delivery-notes.index (menu Transaksi > Surat Jalan).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Surat Jalan'])
@section('body')
    @php $tones = ['Terkonfirmasi' => 'success', 'Draf' => 'neutral', 'Dibatalkan' => 'danger', 'Pembatalan Menunggu Persetujuan' => 'warn']; @endphp
    @include('partials.flash')
    <x-dl2::grid id="delivery-notes" :rows="$rows" actions="worklist" :new-url="auth()->user()->canWrite('ISS') ? route('docs.create', 'issuance') : null" :open-url="auth()->user()->canAccess('ISS') ? url('docs/issuance/{id}') : null"
        search="Cari nomor surat jalan, penerima, trip, plat, sopir..."
        :phone-cards="['title' => 'doc_no', 'sub' => 'party', 'badge' => 'status', 'tones' => $tones, 'meta' => ['trip', 'plate', 'driver']]"
        :columns="[
            ['key' => 'doc_no', 'label' => 'No. Surat Jalan', 'type' => 'identity', 'code' => 'party', 'avatar' => false],
            ['key' => 'doc_at', 'label' => 'Tanggal', 'type' => 'datetime'],
            ['key' => 'lines', 'label' => 'Barang', 'type' => 'number'],
            ['key' => 'trip', 'label' => 'Trip'],
            ['key' => 'plate', 'label' => 'Plat'],
            ['key' => 'driver', 'label' => 'Sopir'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => $tones],
        ]"
        :filters="['trip' => 'Trip', 'status' => 'Status']"
        :stats="[['label' => 'Semua'], ['label' => 'Belum dipakai', 'where' => ['trip' => 'Belum dipakai'], 'tone' => 'warn']]" />
@endsection
