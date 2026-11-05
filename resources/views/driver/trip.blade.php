{{-- REMARK
 Tujuan       : Layar sopir di HP (tanpa login, lewat tautan rahasia): daftar alamat berurutan dengan barang dan perkiraan jam tiba; tombol "Sudah tiba" mencatat jam asli dan ETA alamat berikutnya dihitung ulang.
 Sumber hulu  : DriverTripController@show (trip dengan stops, docs, sched dari DeliveryTrip::schedule).
 Input        : trip, sched [id alamat => {arrive, done, actual}, home].
 Output       : POST driver.arrive / driver.undo per alamat.
 Dipakai oleh : route driver.show.
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Tugas Sopir', 'bodyClass' => 'sstock-bare sstock-hp'])
@section('body')
    @vite('resources/css/driver.css')
    @php
        $hm = fn (int $m) => sprintf('%02d:%02d', intdiv($m, 60) % 24, $m % 60);
        $left = $trip->stops->whereNull('arrived_at')->count();
    @endphp
    <div class="sstock-driver">
        <x-dl::headline>{{ $trip->number() }} · {{ $trip->driver }}</x-dl::headline>
        <p class="sstock-hpc__meta">{{ $trip->trip_date->format('d-m-Y') }} · {{ $trip->vehicle->plate }} {{ $trip->vehicle->name }} · berangkat {{ $trip->depart_time }}@if ($trip->assistant) · bersama {{ $trip->assistant }}@endif</p>
        @if ($trip->status === 'draft')
            <x-dl::alert tone="warn" message="Trip ini belum ditugaskan." />
        @elseif ($left === 0)
            <x-dl::alert tone="success" message="Semua alamat sudah dikunjungi. Perkiraan tiba di kantor {{ $hm($sched['home']) }}." />
        @endif
        @foreach ($trip->stops as $s)
            @php
                $at = $sched[$s->id];
                $items = $s->docs->isNotEmpty() ? $s->docs->flatMap(fn ($d) => $d->lines->map(fn ($l) => [$l->batch->name, (float) $l->qty, $l->batch->unit])) : collect($s->items ?? []);
            @endphp
            <article class="dl-phone-card">
                <h2 class="sstock-hpc__name">{{ $s->seq }}. {{ $s->customer }} <x-dl::badge :tone="$s->kind === 'ant' ? 'info' : 'warn'" :label="$s->kind === 'ant' ? 'Antar' : 'Ambil'" /></h2>
                @if ($s->address)<p class="sstock-hpc__meta">{{ $s->address }}</p>@endif
                <ul>@foreach ($items as $r)<li>{{ $r[0] }}: {{ \Devlib\Locale::number($r[1], 2) }} {{ $r[2] }}</li>@endforeach</ul>
                @if ($s->arrived_at)
                    <p><x-dl::badge tone="success" label="Sudah tiba" /> <strong>{{ $s->arrived_at->format('H:i') }}</strong></p>
                    @if ($trip->status === 'assigned')
                        <form method="post" action="{{ route('driver.undo', [$trip->token, $s->id]) }}"><button type="submit" class="sstock-hpc__btn sstock-hpc__btn--block">Batalkan tanda tiba</button></form>
                    @endif
                @else
                    <p>Perkiraan tiba <strong>{{ $hm($at['arrive']) }}</strong>, selesai {{ $hm($at['done']) }}</p>
                    @if ($trip->status === 'assigned')
                        <form method="post" action="{{ route('driver.arrive', [$trip->token, $s->id]) }}"><button type="submit" class="sstock-hpc__btn sstock-hpc__btn--go sstock-hpc__btn--block">Sudah tiba</button></form>
                    @endif
                @endif
            </article>
        @endforeach
        <p class="sstock-hpc__meta">Kembali ke kantor sekitar {{ $hm($sched['home']) }}.</p>
    </div>
@endsection
