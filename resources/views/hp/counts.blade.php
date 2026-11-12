{{-- REMARK
 Tujuan       : Tab Opname tampilan HP: Stok Opname sebagai kartu (nomor, tanggal, cakupan, status, kemajuan) dengan tombol Hitung untuk opname yang sedang dihitung; Supervisor mengelola (mulai, tinjau, setujui) lewat halaman desktop di dalam kerangka HP.
 Sumber hulu  : StockCountController@phoneIndex.
 Input        : counts (id, doc_no, date, scope, status, progress, counting), sup (Supervisor).
 Output       : kartu per opname; Hitung -> hp.count; Kelola -> hp.open SO.
 Dipakai oleh : route hp.counts.
 Status       : Aktif.
--}}
@extends('hp.layout', ['title' => 'Stok Opname', 'active' => 'count', 'back' => route('home')])
@section('content')
    @include('partials.flash')
    @forelse ($counts as $c)
        <article class="ak-pcard">
            <h2 class="sstock-hpc__name">{{ $c['doc_no'] }} <x-dl2::badge :tone="$c['tone']" :label="raw($c['status'])" /></h2>
            <p class="sstock-hpc__meta">{{ $c['date'] }} · {{ $c['scope'] }}</p>
            <p>Terisi {{ $c['progress'] }}</p>
            @if ($c['counting'])
                <a class="sstock-hpc__btn sstock-hpc__btn--go sstock-hpc__btn--block" href="{{ route('hp.count', $c['id']) }}">Hitung sekarang</a>
            @elseif ($sup)
                <a class="sstock-hpc__btn sstock-hpc__btn--block" href="{{ route('hp.open', 'SO') }}">Kelola (Supervisor)</a>
            @endif
        </article>
    @empty
        <x-dl2::alert tone="info" message="Belum ada Stok Opname." />
    @endforelse
    @if ($sup)
        <a class="sstock-hpc__btn sstock-hpc__btn--block" href="{{ route('hp.open', 'SO') }}">Mulai atau tinjau opname (halaman desktop)</a>
    @endif
@endsection
