{{-- REMARK
 Tujuan       : Detail satu kendaraan (GA, modul GVM): (a) dokumen dan masa berlaku (pajak tahunan, STNK + plat 5 tahunan, KIR hanya bila wajib, asuransi) dengan sisa waktu, status, dan lampiran;
                (b) jadwal perawatan tiap butir rencana jenis kendaraan (interval km / bulan, terakhir dikerjakan, berikutnya, sisa, batang kemajuan, status); (c) riwayat servis.
 Sumber hulu  : FleetController@show (vehicle, o = VehicleCare::overview, services, yearCost).
 Input        : vehicle, o, services, historyLimit, yearCost, year.
 Output       : headline + status, kotak ringkasan, tiga tabel, tombol input (Perpanjang dokumen, Catat servis, Catat odometer) bagi yang boleh menulis modulnya.
 Dipakai oleh : route fleet.show (Buka dari Monitoring Kendaraan).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Kendaraan'])
@section('body')
    @php
        use App\Services\VehicleCare;
        $tone = VehicleCare::TONES;
        $badge = fn (?string $s) => $s ? $tone[VehicleCare::STATUS[$s]] : 'neutral';
        $label = fn (?string $s) => $s ? VehicleCare::STATUS[$s] : '';
        $pajak = $o['docs']['pajak'];
        $next = $o['next'];
        $u = auth()->user();
    @endphp
    @include('partials.flash')
    <x-dl::headline>{{ $vehicle->plate }} · {{ $vehicle->name }} <x-dl::badge :tone="$badge($o['status'])" :label="$label($o['status'])" /></x-dl::headline>
    <p class="dl-note">{{ $vehicle->type->name }}@if ($vehicle->year) · tahun {{ $vehicle->year }}@endif · {{ $vehicle->kir_required ? 'Wajib KIR' : 'Tidak wajib KIR' }}@unless ($vehicle->is_active) · NONAKTIF @endunless</p>
    <x-dl::kpi-row>
        <x-dl::stat-box label="Odometer terakhir" :value="VehicleCare::km($vehicle->odometer_km)" tone="accent" :sub="$vehicle->odometer_at?->format('d-m-Y')" />
        <x-dl::stat-box label="Pajak tahunan" :value="$pajak['doc'] ? VehicleCare::daysText($pajak['days']) : 'Belum dicatat'" :tone="$pajak['status'] ? $badge($pajak['status']) : 'neutral'" :sub="$pajak['doc'] ? 'Berlaku sampai '.$pajak['valid_until']->format('d-m-Y') : null" />
        <x-dl::stat-box label="Servis berikutnya" :value="$next ? $next['name'] : 'Tidak ada jadwal'" :tone="$next ? $badge($next['status']) : 'neutral'" :sub="$next ? $next['left'] : null" />
        <x-dl::stat-box :label="'Biaya servis '.$year" :value="VehicleCare::rp($yearCost)" tone="neutral" />
    </x-dl::kpi-row>
    <x-dl::action-bar align="start">
        <x-dl::button variant="secondary" icon="back" label="Kembali" :href="route('fleet.index')" />
        @if ($vehicle->is_active)
        @if ($u->canWrite('GVD'))
            <x-dl::button variant="secondary" icon="fa-file-signature" label="Perpanjang Dokumen" :href="route('vehicle-docs.create', ['vehicle' => $vehicle->id])" />
        @endif
        @if ($u->canWrite('GVS'))
            <x-dl::button variant="secondary" icon="fa-screwdriver-wrench" label="Catat Servis" :href="route('vehicle-services.create', ['vehicle' => $vehicle->id])" />
        @endif
        @if ($u->canWrite('GVO'))
            <x-dl::button variant="secondary" icon="fa-gauge-high" label="Catat Odometer" :href="route('vehicle-odometers.create', ['vehicle' => $vehicle->id])" />
        @endif
        @endif
    </x-dl::action-bar>

    <x-dl::divider label="Dokumen dan masa berlaku" />
    <div class="dl-table-wrap dl-table--stack">
        <table class="dl-table is-striped">
            <thead><tr><th>Dokumen</th><th>Berlaku sampai</th><th>Sisa waktu</th><th>No. dokumen</th><th>Lampiran</th><th>Status</th></tr></thead>
            <tbody>
                @foreach ($o['docs'] as $d)
                    <tr>
                        <td data-label="Dokumen"><b>{{ $d['label'] }}</b></td>
                        <td data-label="Berlaku sampai">{{ $d['valid_until']?->format('d-m-Y') ?? '—' }}</td>
                        <td data-label="Sisa waktu">{{ $d['doc'] ? VehicleCare::daysText($d['days']) : '—' }}</td>
                        <td data-label="No. dokumen">{{ $d['doc']?->doc_no ?: '—' }}</td>
                        <td data-label="Lampiran">@if ($d['doc']?->attachment_path)<a href="{{ route('fleet.file', ['doc', $d['doc']->id]) }}" target="_blank" rel="noopener">{{ $d['doc']->attachment_name ?: 'Lihat' }}</a>@else — @endif</td>
                        <td data-label="Status">
                            @if (! $d['required'])
                                <x-dl::badge tone="neutral" label="Tidak wajib" />
                            @elseif (! $d['doc'])
                                <x-dl::badge :tone="$d['status'] ? 'warn' : 'neutral'" label="Belum dicatat" />
                            @else
                                <x-dl::badge :tone="$badge($d['status'])" :label="$label($d['status'])" />
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <p class="dl-note">Masa berlaku yang dipakai adalah tanggal berlaku-sampai terbesar per jenis dokumen. Pajak tahunan (PKB + SWDKLLJ) dan asuransi 12 bulan, STNK + plat 5 tahun (60 bulan), KIR 6 bulan. Status Segera bila sisa waktu {{ VehicleCare::soonDays() }} hari atau kurang.</p>

    <x-dl::divider label="Jadwal perawatan" />
    @if ($o['items'])
        <div class="dl-table-wrap dl-table--stack">
            <table class="dl-table is-striped">
                <thead><tr><th>Pekerjaan</th><th>Interval</th><th>Terakhir dikerjakan</th><th>Berikutnya</th><th>Sisa</th><th>Kemajuan</th><th>Status</th></tr></thead>
                <tbody>
                    @foreach ($o['items'] as $i)
                        <tr>
                            <td data-label="Pekerjaan"><b>{{ $i['name'] }}</b></td>
                            <td data-label="Interval">{{ $i['interval'] }}</td>
                            <td data-label="Terakhir dikerjakan">
                                @if ($i['done'])
                                    {{ $i['last_at']->format('d-m-Y') }} · {{ VehicleCare::km($i['last_km']) }}
                                @else
                                    Belum pernah · acuan {{ $i['base_at']->format('d-m-Y') }}, {{ VehicleCare::km($i['base_km']) }}
                                @endif
                            </td>
                            <td data-label="Berikutnya">{{ collect([$i['next_km'] !== null ? VehicleCare::km($i['next_km']) : null, $i['next_at']?->format('d-m-Y')])->filter()->implode(' / ') }}</td>
                            <td data-label="Sisa">{{ $i['left'] }}</td>
                            <td data-label="Kemajuan"><x-dl::progress :value="$i['progress']" :tone="$badge($i['status'])" :label="$i['progress'].'%'" /></td>
                            <td data-label="Status"><x-dl::badge :tone="$badge($i['status'])" :label="$label($i['status'])" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="dl-note">Jadwal dihitung dari servis terakhir tiap pekerjaan (odometer + interval km, tanggal + interval bulan); yang tercapai lebih dulu menentukan. Pekerjaan yang belum pernah dicatat dihitung dari odometer awal kendaraan. Segera bila sisa {{ number_format(VehicleCare::soonKm(), 0, ',', '.') }} km atau {{ VehicleCare::soonDays() }} hari atau kurang.</p>
    @else
        <p class="dl-note">Jenis kendaraan ini belum punya rencana perawatan. Isi di Jenis Kendaraan.</p>
    @endif

    <x-dl::divider label="Riwayat servis" />
    @if ($services->isNotEmpty())
        <div class="dl-table-wrap dl-table--stack">
            <table class="dl-table is-striped">
                <thead><tr><th>Tanggal</th><th>Pekerjaan</th><th>Bengkel</th><th>Odometer</th><th>Biaya</th></tr></thead>
                <tbody>
                    @foreach ($services as $s)
                        <tr>
                            <td data-label="Tanggal">{{ $s->serviced_at->format('d-m-Y') }}</td>
                            <td data-label="Pekerjaan">{{ $s->lines->pluck('name')->implode(', ') ?: ($s->note ?: '—') }}</td>
                            <td data-label="Bengkel">{{ $s->workshop ?: '—' }}</td>
                            <td data-label="Odometer">{{ VehicleCare::km($s->odometer_km) }}</td>
                            <td data-label="Biaya">{{ VehicleCare::rp($s->cost) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($services->count() >= $historyLimit)
            <p class="dl-note">Menampilkan {{ $historyLimit }} servis terakhir.</p>
        @endif
    @else
        <p class="dl-note">Belum ada servis tercatat.</p>
    @endif
@endsection
