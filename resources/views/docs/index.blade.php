{{-- REMARK
 Tujuan       : Daftar dokumen stok satu jenis (Receiving / Issuance / Opening Balance / Stock Adjustment) sebagai Grid Transactional POLOS (cepat dipindai untuk data banyak): nomor dokumen tebal di kolom pertama, hanya kolom Status yang berwarna; baris dibuka menampilkan item-itemnya.
 Sumber hulu  : StockDocumentController@index. Kolom nilai hanya untuk Supervisor.
 Input        : t (StockDocumentType), rows, sup.
 Output       : x-dl2::grid; klik 2x / Open membuka form dokumen, + New Record membuat dokumen baru.
 Dipakai oleh : route docs.index (menu Transactions).
 Status       : Aktif.
--}}
@extends('layout', ['title' => $t->label()])
@section('body')
    @php
        $refLabel = match ($t) { \App\Enums\StockDocumentType::Receiving => 'Reference No.', \App\Enums\StockDocumentType::Issuance => 'Department', default => 'Reason' };
        $isAdj = $t === \App\Enums\StockDocumentType::Adjustment;
        $tones = ['Terkonfirmasi' => 'success', 'Draf' => 'neutral', 'Menunggu Persetujuan' => 'warn', 'Disetujui' => 'success', 'Dibatalkan' => 'danger', 'Pembatalan Menunggu Persetujuan' => 'warn', 'Ditolak' => 'danger'];
    @endphp
    @include('partials.flash')
    <x-dl2::grid :id="'docs-'.$t->slug()" :rows="$rows" actions="worklist" :new-url="route('docs.create', $t->slug())" :open-url="url('docs/'.$t->slug().'/{id}')" search="Search document, party, reference..."
        :columns="array_values(array_filter([
            ['key' => 'doc_no', 'label' => 'Document', 'type' => 'identity', 'code' => 'party', 'avatar' => false],
            ['key' => 'doc_at', 'label' => 'Date & Time', 'type' => 'datetime'],
            ['key' => 'ref', 'label' => $refLabel],
            $sup ? ['key' => 'value', 'label' => 'Value', 'type' => 'money', 'decimals' => 2, 'sum' => true] : null,
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => $tones],
            ['key' => 'user', 'label' => 'Entered by'],
        ]))"
        {{-- Tampilan HP: daftar kartu (nomor dokumen + pihak, lencana Status, tanggal, referensi, nilai bagi Supervisor); desktop tetap tabel. --}}
        :phone-cards="['title' => 'doc_no', 'sub' => 'party', 'badge' => 'status', 'tones' => $tones, 'meta' => array_values(array_filter(['doc_at', 'ref', $sup ? 'value' : null, 'user']))]"
        :detail="['key' => 'lines', 'title' => 'Items', 'columns' => array_values(array_filter([
            ['key' => 'name', 'label' => 'Item', 'strong' => true],
            ['key' => 'code', 'label' => 'Code'],
            ['key' => 'qty', 'label' => $isAdj ? 'Difference' : 'Qty', 'type' => 'number'],
            ['key' => 'unit', 'label' => 'Unit'],
            $sup ? ['key' => 'cost', 'label' => 'Unit Cost', 'type' => 'money'] : null,
        ]))]"
        :stats="$isAdj ? [['label' => 'All'], ['label' => 'Waiting approval', 'where' => ['status' => 'Menunggu Persetujuan'], 'tone' => 'warn'], ['label' => 'Draft', 'where' => ['status' => 'Draf']]] : [['label' => 'All']]" />
@endsection
