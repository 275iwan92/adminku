{{-- REMARK
 Tujuan       : Stok satu barang per batch pada Cari Barang tampilan HP, urutan FEFO (kedaluwarsa paling awal dulu): jumlah, tanggal kedaluwarsa, sisa hari, lencana KEDALUWARSA / HAMPIR. Nilai stok hanya untuk Supervisor.
 Sumber hulu  : HpController@product (product dengan available_qty, batches, scanned, value).
 Input        : product, batches (stok > 0, urut FEFO), scanned (kode batch yang dipindai), value (null untuk bukan Supervisor).
 Output       : kartu ringkasan barang + satu kartu per batch.
 Dipakai oleh : route hp.find.show.
 Status       : Aktif.
--}}
@extends('hp.layout')
@section('content')
    @include('hp._find-box', ['q' => ''])
    @php $below = $product->reorder_point > 0 && (float) $product->available_qty <= $product->reorder_point; @endphp
    <article class="ak-pcard">
        <h2 class="sstock-hpc__name">{{ $product->name }} <x-dl2::badge :tone="$below ? 'danger' : 'success'" :label="$below ? 'PESAN ULANG' : 'AMAN'" /></h2>
        <p class="sstock-hpc__meta">{{ $product->code }}@if ($product->location) · {{ $product->location }}@endif</p>
        <p><strong>{{ \Devlib2\Locale::number($product->available_qty, 2) }} {{ $product->unit }}</strong> tersedia · titik pesan ulang {{ \Devlib2\Locale::number($product->reorder_point, 2) }}</p>
        @if ($value !== null)
            <p class="sstock-hpc__meta">Nilai stok IDR {{ \Devlib2\Locale::number($value, 0) }}</p>
        @endif
    </article>
    @forelse ($batches as $b)
        @php
            $state = $b->expiryState();
            $left = $b->expiry_date ? (int) today()->diffInDays($b->expiry_date, false) : null;
        @endphp
        <article class="ak-pcard" @if ($scanned && strcasecmp($scanned, $b->code) === 0) data-scanned @endif>
            <h2 class="sstock-hpc__name">{{ $b->code }}@if ($state) <x-dl2::badge :tone="$state === 'KEDALUWARSA' ? 'danger' : 'warn'" :label="$state" />@endif</h2>
            <p><strong>{{ \Devlib2\Locale::number($b->stock_qty, 2) }} {{ $b->unit }}</strong></p>
            <p class="sstock-hpc__meta">
                @if ($b->expiry_date)
                    Kedaluwarsa {{ \Devlib2\Locale::d($b->expiry_date) }} · {{ $left > 0 ? 'sisa '.$left.' hari' : ($left === 0 ? 'hari ini' : 'lewat '.abs($left).' hari') }}
                @else
                    Tanpa kedaluwarsa
                @endif
                @if ($b->location) · {{ $b->location }}@endif
            </p>
        </article>
    @empty
        <x-dl2::alert tone="info" message="Barang ini belum punya stok di batch mana pun." />
    @endforelse
@endsection
