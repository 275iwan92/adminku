{{-- REMARK
 Tujuan       : Layar sopir di HP (tanpa login, lewat tautan rahasia): daftar alamat berurutan dengan barang dan perkiraan jam tiba; tombol "Sudah tiba" mencatat jam asli dan ETA alamat berikutnya dihitung ulang.
 Sumber hulu  : DriverTripController@show (trip dengan stops, docs, sched dari DeliveryTrip::schedule).
 Input        : trip, sched [id alamat => {arrive, done, actual}, home].
 Output       : POST driver.arrive / driver.undo per alamat; bagian HP saja (resources/js/driver.js, tersembunyi di desktop / tanpa HTTPS): tombol Pindai kode (driver.scan) dan Bagikan lokasi GPS (driver.position); manifest PWA driver.manifest.
 Dipakai oleh : route driver.show.
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Tugas Sopir', 'bodyClass' => 'sstock-bare sstock-hp'])
@section('body')
    @push('head')
        <link rel="manifest" href="{{ route('driver.manifest', $trip->token) }}">
        <meta name="theme-color" content="#0f766e">
        <link rel="apple-touch-icon" href="{{ asset('icons/adminku-192.png') }}">
    @endpush
    @vite(['resources/css/driver.css', 'resources/js/driver.js'])
    @php
        $hm = fn (int $m) => sprintf('%02d:%02d', intdiv($m, 60) % 24, $m % 60);
        $left = $trip->stops->whereNull('arrived_at')->count();
    @endphp
    <div class="sstock-driver" data-driver>
        <x-dl::headline>{{ $trip->number() }} · {{ $trip->driver }}</x-dl::headline>
        <p class="sstock-hpc__meta">{{ $trip->trip_date->format('d-m-Y') }} · {{ $trip->vehicle->plate }} {{ $trip->vehicle->name }} · berangkat {{ $trip->depart_time }}@if ($trip->assistant) · bersama {{ $trip->assistant }}@endif</p>
        @if ($trip->status === 'draft')
            <x-dl::alert tone="warn" message="Trip ini belum ditugaskan." />
        @elseif ($left === 0)
            <x-dl::alert tone="success" message="Semua alamat sudah dikunjungi. Perkiraan tiba di kantor {{ $hm($sched['home']) }}." />
        @endif
        @if ($trip->status === 'assigned' && $left > 0)
            <div class="sstock-driver__gps" data-hp-only hidden data-gps data-url="{{ route('driver.position', $trip->token) }}">
                <span data-gps-state>Lokasi tidak dibagikan</span>
                <button type="button" class="sstock-hpc__btn" data-gps-toggle>Bagikan lokasi</button>
            </div>
        @endif
        <p class="sstock-hpc__meta" data-desktop-note>Pindai kode dan berbagi lokasi hanya tersedia di HP: buka tautan ini di HP sopir (HTTPS).</p>
        @foreach ($trip->stops as $s)
            @php
                $at = $sched[$s->id];
                $items = $s->docs->isNotEmpty() ? $s->docs->flatMap(fn ($d) => $d->lines->map(fn ($l) => [$l->batch->name, (float) $l->qty, $l->batch->unit])) : collect($s->items ?? []);
            @endphp
            <article class="dl-phone-card">
                <h2 class="sstock-hpc__name">{{ $s->seq }}. {{ $s->customer }} <x-dl::badge :tone="$s->kind === 'ant' ? 'info' : 'warn'" :label="$s->kind === 'ant' ? 'Antar' : 'Ambil'" /></h2>
                @if ($s->address)<p class="sstock-hpc__meta">{{ $s->address }}</p>@endif
                @if ($s->scanned_at)
                    <p><x-dl::badge :tone="$s->scan_ok ? 'success' : 'danger'" :label="$s->scan_ok ? 'Kode cocok' : 'Kode tidak cocok'" /> {{ $s->scan_code }} · {{ $s->scanned_at->format('H:i') }}</p>
                @endif
                @if ($trip->status === 'assigned')
                    <button type="button" class="sstock-hpc__btn sstock-hpc__btn--block" data-hp-only hidden data-scan data-url="{{ route('driver.scan', [$trip->token, $s->id]) }}">{{ $s->scanned_at ? 'Pindai ulang kode' : 'Pindai kode' }}</button>
                @endif
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
        <dialog data-scan-dialog class="sstock-driver__scan" data-hp-only hidden>
            <video playsinline muted></video>
            <p data-scan-msg></p>
            <button type="button" class="sstock-hpc__btn sstock-hpc__btn--block" data-scan-close>Tutup</button>
        </dialog>
        <p class="sstock-hpc__meta">Kembali ke kantor sekitar {{ $hm($sched['home']) }}.</p>
    </div>
@endsection
