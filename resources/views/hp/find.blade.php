{{-- REMARK
 Tujuan       : Cari Barang tampilan HP: kolom cari / pindai, lalu hasil sebagai kartu barang (nama, kode, lencana Level AMAN / PESAN ULANG, stok tersedia + satuan, lokasi); ketuk kartu = stok per batch.
 Sumber hulu  : HpController@find (q, products dengan available_qty).
 Input        : q, products (null = belum mencari), limit.
 Output       : kartu hasil -> hp.find.show.
 Dipakai oleh : route hp.find.
 Status       : Aktif.
--}}
@extends('hp.layout')
@section('content')
    @include('hp._find-box', ['q' => $q])
    @if ($products === null)
        <x-dl2::alert tone="info" message="Ketik atau pindai kode barang, kode batch, nama, atau kode lokasi. Kode lengkap langsung membuka barangnya." />
    @elseif ($products->isEmpty())
        <x-dl2::alert tone="warn" :message="raw('Tidak ada barang yang cocok dengan “'.$q.'”.')" />
    @else
        @foreach ($products as $p)
            @php $below = $p->reorder_point > 0 && (float) $p->available_qty <= $p->reorder_point; @endphp
            <article class="ak-pcard">
                <a class="sstock-hpc__cardlink" href="{{ route('hp.find.show', $p) }}">
                    <h2 class="sstock-hpc__name">{{ $p->name }} <x-dl2::badge :tone="$below ? 'danger' : 'success'" :label="$below ? 'PESAN ULANG' : 'AMAN'" /></h2>
                    <p class="sstock-hpc__meta">{{ $p->code }}@if ($p->location) · {{ $p->location }}@endif</p>
                    <p><strong>{{ \Devlib2\Locale::number($p->available_qty, 2) }} {{ $p->unit }}</strong> tersedia</p>
                </a>
            </article>
        @endforeach
        @if ($products->count() >= $limit)
            <x-dl2::alert tone="info" :message="'Menampilkan '.$limit.' hasil pertama. Persempit kata kunci.'" />
        @endif
    @endif
@endsection
