{{-- REMARK
 Tujuan       : Detail satu AC (GA, modul GAM), meniru Detail Kendaraan: judul + status, kotak ringkasan (cuci dan servis besar berikutnya, kerusakan 12 bulan, biaya 12 bulan), tombol (Jadwalkan Kunjungan, Ubah Data AC),
                tabel kondisi dan jadwal, riwayat perawatan (kunjungan selesai: temuan, tindakan, jasa, foto bukti), dan spare part yang diganti. Hanya-baca; input hasil tetap di layar Kunjungan.
 Sumber hulu  : AcCareController@show (unit, o = AcCare::overview, lines, breakdowns, open, yearCost, yearParts).
 Input        : unit, o, lines, historyLimit, breakdowns, open, yearCost, yearParts.
 Output       : headline + status, kotak ringkasan, tiga tabel; tautan kunjungan dan foto hanya bagi yang berhak GAV.
 Dipakai oleh : route ac-care.show (Buka dari Monitoring AC).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Detail AC'])
@section('body')
    @php
        use App\Services\AcCare;
        use App\Services\VehicleCare;
        use App\Support\Decimal;
        $tones = AcCare::TONES;
        $u = auth()->user();
        $status = $open ? 'Rusak' : $o['label'];
        $canVisits = $u->canAccess('GAV');
    @endphp
    @include('partials.flash')
    <x-dl::headline>{{ $unit->asset_code }} · {{ $unit->room->name }} <x-dl::badge :tone="$tones[$status]" :label="$status" /></x-dl::headline>
    <p class="dl-note">{{ $unit->typeText() }} · {{ $unit->room->area->name }} ({{ $unit->room->code }})@if ($unit->installed_at) · terpasang {{ $unit->installed_at->format('d-m-Y') }}@endif @unless ($unit->is_active) · NONAKTIF @endunless</p>
    <x-dl::kpi-row>
        <x-dl::stat-box label="Cuci rutin berikutnya" :value="$o['next_wash']->format('d-m-Y')" tone="accent" :sub="VehicleCare::daysText(VehicleCare::days(today(), $o['next_wash']))" />
        <x-dl::stat-box label="Servis besar berikutnya" :value="$o['next_major']->format('d-m-Y')" tone="accent" :sub="VehicleCare::daysText(VehicleCare::days(today(), $o['next_major']))" />
        <x-dl::stat-box label="Kerusakan 12 bulan" :value="(string) $breakdowns" :tone="$open ? 'danger' : 'neutral'" :sub="$open ? 'Ada yang belum selesai' : null" />
        <x-dl::stat-box label="Biaya 12 bulan" :value="VehicleCare::rp($yearCost)" tone="neutral" :sub="'Spare part '.VehicleCare::rp($yearParts)" />
    </x-dl::kpi-row>
    <x-dl::action-bar align="start">
        <x-dl::button class="sstock-btn-slate" variant="secondary" icon="back" label="Kembali" :href="route('ac-care.index')" />
        @if ($unit->is_active && $u->canWrite('GAS') && ! $o['open'])
            <x-dl::button class="sstock-btn-teal" variant="secondary" icon="fa-calendar-plus" label="Jadwalkan Kunjungan" :href="route('ac-schedule.index', ['pick' => $unit->id])" />
        @endif
        @if ($u->canWrite('GAK'))
            <x-dl::button class="sstock-btn-amber" variant="secondary" icon="fa-pen-to-square" label="Ubah Data AC" :href="route('ac-units.show', $unit)" />
        @endif
    </x-dl::action-bar>
    @if ($o['open'])
        <p class="dl-note">Kunjungan terbuka: {{ $o['open']['date']->format('d-m-Y') }} · {{ $o['open']['no'] }} · {{ AcCare::ORDER_STATUS[$o['open']['status']] }}@if ($canVisits) · <a href="{{ route('ac-visits.show', $o['open']['order_id']) }}">Buka kunjungan</a>@endif</p>
    @endif

    <x-dl::divider label="Kondisi dan jadwal" />
    <div class="dl-table-wrap dl-table--stack">
        <table class="dl-table is-striped">
            <thead><tr><th>Butir</th><th>Terakhir</th><th>Berikutnya</th><th>Sisa</th><th>Status</th></tr></thead>
            <tbody>
                @foreach ([['Cuci rutin ('.AcCare::washMonths().' bln)', $o['last_wash'], $o['next_wash']], ['Servis besar ('.AcCare::majorMonths().' bln)', $o['last_major'], $o['next_major']]] as [$name, $last, $next])
                    @php($days = VehicleCare::days(today(), $next))
                    @php($st = $days < 0 ? 'terlambat' : ($days <= AcCare::soonDays() ? 'segera' : 'aman'))
                    <tr>
                        <td data-label="Butir"><b>{{ $name }}</b></td>
                        <td data-label="Terakhir">{{ $last?->format('d-m-Y') ?? 'Belum pernah' }}</td>
                        <td data-label="Berikutnya">{{ $next->format('d-m-Y') }}</td>
                        <td data-label="Sisa">{{ VehicleCare::daysText($days) }}</td>
                        <td data-label="Status"><x-dl::badge :tone="$tones[AcCare::STATUS[$st]]" :label="AcCare::STATUS[$st]" /></td>
                    </tr>
                @endforeach
                @php($last = $lines->first())
                <tr>
                    <td data-label="Butir"><b>Kondisi terakhir</b></td>
                    <td data-label="Terakhir" colspan="3">{{ $last ? $last->done_at->format('d-m-Y').': '.(collect([$last->findings, $last->action ? 'tindakan '.$last->action : null])->filter()->implode('; ') ?: 'tanpa catatan') : 'Belum ada kunjungan selesai.' }}</td>
                    <td data-label="Status"><x-dl::badge :tone="$open ? 'danger' : 'success'" :label="$open ? 'Rusak' : 'Normal'" /></td>
                </tr>
            </tbody>
        </table>
    </div>
    <p class="dl-note">Cuci rutin tiap {{ AcCare::washMonths() }} bulan dan servis besar tiap {{ AcCare::majorMonths() }} bulan sejak pekerjaan terakhir (servis besar sudah mencakup cuci). Segera bila sisa {{ AcCare::soonDays() }} hari atau kurang.</p>

    <x-dl::divider label="Riwayat perawatan" />
    @if ($lines->isNotEmpty())
        <div class="dl-table-wrap dl-table--stack">
            <table class="dl-table is-striped">
                <thead><tr><th>Tanggal</th><th>Kunjungan</th><th>Jenis</th><th>Temuan dan tindakan</th><th>Vendor</th><th>Jasa</th><th>Foto</th></tr></thead>
                <tbody>
                    @foreach ($lines as $l)
                        <tr>
                            <td data-label="Tanggal">{{ $l->done_at->format('d-m-Y') }}</td>
                            <td data-label="Kunjungan">@if ($canVisits)<a href="{{ route('ac-visits.show', $l->order_id) }}">{{ $l->order->no }}</a>@else{{ $l->order->no }}@endif</td>
                            <td data-label="Jenis">{{ AcCare::JOBS[$l->job] ?? $l->job }}@if ($l->order->isBreakdown()) <x-dl::badge tone="danger" label="Breakdown" />@endif</td>
                            <td data-label="Temuan dan tindakan">{{ collect([$l->findings, $l->cause ? 'Penyebab: '.$l->cause : null, $l->action ? 'Tindakan: '.$l->action : null])->filter()->implode('. ') ?: ($l->note ?: '—') }}</td>
                            <td data-label="Vendor">{{ $l->order->vendor?->name ?: '—' }}</td>
                            <td data-label="Jasa">{{ $l->labor_cost !== null ? VehicleCare::rp($l->labor_cost) : '—' }}</td>
                            <td data-label="Foto">
                                @php($shots = collect(['before' => 'Sebelum', 'after' => 'Sesudah'])->filter(fn ($t, $k) => $l->{"photo_{$k}_path"}))
                                @if ($canVisits && $shots->isNotEmpty())
                                    @foreach ($shots as $k => $t)<a href="{{ route('ac-visits.file', [$l->id, $k]) }}" target="_blank" rel="noopener">{{ $t }}</a>@unless ($loop->last) · @endunless @endforeach
                                @else — @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($lines->count() >= $historyLimit)
            <p class="dl-note">Menampilkan {{ $historyLimit }} pekerjaan terakhir.</p>
        @endif
    @else
        <p class="dl-note">Belum ada perawatan selesai tercatat.</p>
    @endif

    <x-dl::divider label="Spare part diganti atau diperbaiki" />
    @php($parts = $lines->flatMap(fn ($l) => $l->parts->map(fn ($p) => [$l, $p])))
    @if ($parts->isNotEmpty())
        <div class="dl-table-wrap dl-table--stack">
            <table class="dl-table is-striped">
                <thead><tr><th>Tanggal</th><th>Spare part</th><th>Jumlah</th><th>Biaya</th><th>Kunjungan</th></tr></thead>
                <tbody>
                    @foreach ($parts as [$l, $p])
                        <tr>
                            <td data-label="Tanggal">{{ $l->done_at->format('d-m-Y') }}</td>
                            <td data-label="Spare part"><b>{{ $p->name }}</b></td>
                            <td data-label="Jumlah">{{ Decimal::fmtQty($p->qty) }}</td>
                            <td data-label="Biaya">{{ $p->cost !== null ? VehicleCare::rp($p->cost) : '—' }}</td>
                            <td data-label="Kunjungan">{{ $l->order->no }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="dl-note">Belum ada spare part tercatat. Spare part tidak terhubung ke stok gudang.</p>
    @endif
@endsection
