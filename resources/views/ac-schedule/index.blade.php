{{-- REMARK
 Tujuan       : Penjadwalan AC (GA, modul GAS), satu layar: kotak ringkasan (total AC, terlambat dicuci, jatuh tempo dalam ambang, terjadwal minggu ini), kalender minggu Senin-Sabtu dengan isi / kapasitas per hari (klik hari = tanggal
                kunjungan terpilih; hari penuh bertanda), daftar AC yang perlu dibersihkan (centang, paling terlambat dulu, saring per area), tombol Jadwalkan yang dipilih, Susun otomatis, dan pemilih kapasitas harian.
 Sumber hulu  : AcScheduleController@index (vendors, vendorId, calendar, week, selected, cap, rows, areas, areaId, all, time, days, window, summary, canWrite, canVisits, dateText, left).
 Input        : lihat sumber hulu.
 Output       : GET saring (vendor, area, tampilan); POST ac-schedule.store / auto / capacity.
 Dipakai oleh : route ac-schedule.index (menu GA > Penjadwalan AC).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Penjadwalan AC'])
@section('body')
    @php
        use App\Services\AcCare;
        $tone = AcCare::TONES;
        $query = fn (array $extra = []) => route('ac-schedule.index', array_filter(array_merge(['vendor' => $vendorId, 'week' => $week->toDateString(), 'date' => $selected?->toDateString(), 'area' => $areaId ?: null, 'show' => $all ? 'semua' : null], $extra), fn ($v) => $v !== null));
        $dayName = [1 => 'Sen', 2 => 'Sel', 3 => 'Rab', 4 => 'Kam', 5 => 'Jum', 6 => 'Sab'];
        $oldUnits = array_map('intval', (array) old('units', []));
    @endphp
    @include('partials.flash')
    <x-dl::kpi-row>
        <x-dl::stat-box label="Total AC" :value="(string) $summary['total']" tone="accent" sub="AC aktif" />
        <x-dl::stat-box label="Terlambat dicuci" :value="(string) $summary['late']" :tone="$summary['late'] ? 'danger' : 'success'" sub="Jatuh tempo sudah lewat" />
        <x-dl::stat-box label="Jatuh tempo" :value="(string) $summary['soon']" :tone="$summary['soon'] ? 'warn' : 'success'" :sub="'Dalam '.AcCare::soonDays().' hari'" />
        <x-dl::stat-box label="Terjadwal minggu ini" :value="(string) $summary['week']" tone="neutral" sub="AC pada kunjungan minggu ini" />
    </x-dl::kpi-row>

    @if (! $vendors)
        <x-dl::empty-state title="Belum ada vendor AC aktif" message="Tambahkan vendor di menu Vendor AC lebih dulu." icon="fa-handshake" />
    @else
    <x-dl::action-form method="get" :action="route('ac-schedule.index')" class="dl-report-filter">
        <input type="hidden" name="week" value="{{ $week->toDateString() }}">
        @if ($selected)
            <input type="hidden" name="date" value="{{ $selected->toDateString() }}">
        @endif
        <x-dl::form.row :cols="4">
            <x-dl::field.select name="vendor" label="Vendor" :options="$vendors" :value="$vendorId" />
            <x-dl::field.select name="area" label="Area" :options="['' => 'Semua area'] + $areas" :value="$areaId ?: ''" />
            <x-dl::field.select name="show" label="Tampilkan" :options="['perlu' => 'AC yang perlu dibersihkan', 'semua' => 'Semua AC aktif']" :value="$all ? 'semua' : 'perlu'" />
        </x-dl::form.row>
        <x-dl::action-bar>
            <x-dl::button type="submit" icon="fa-filter" label="Terapkan" size="sm" />
        </x-dl::action-bar>
    </x-dl::action-form>

    <x-dl::divider :label="'Kalender kunjungan · '.$week->format('d-m-Y').' sampai '.$week->copy()->addDays(5)->format('d-m-Y')" />
    <x-dl::action-bar align="start">
        <x-dl::button variant="secondary" size="sm" icon="fa-chevron-left" label="Minggu sebelumnya" :href="$query(['week' => $week->copy()->subWeek()->toDateString(), 'date' => null])" />
        <x-dl::button variant="secondary" size="sm" label="Minggu ini" :href="$query(['week' => null, 'date' => null])" />
        <x-dl::button variant="secondary" size="sm" icon="fa-chevron-right" label="Minggu berikutnya" :href="$query(['week' => $week->copy()->addWeek()->toDateString(), 'date' => null])" />
    </x-dl::action-bar>
    <div class="sstock-week">
        @foreach ($calendar as $c)
            @php
                $full = $c['n'] >= $cap;
                $off = $c['past'] || $c['closed'];
                $on = $selected && $selected->isSameDay($c['date']);
                $cls = 'sstock-day'.($on ? ' is-on' : '').($full && ! $off ? ' is-full' : '').($off ? ' is-off' : '');
            @endphp
            @if ($off)
                <div class="{{ $cls }}">
                    <b>{{ $dayName[$c['date']->dayOfWeekIso] }} {{ $c['date']->format('d-m') }}</b>
                    <span class="sstock-day__load">{{ $c['n'] }}/{{ $cap }}</span>
                    <small>{{ $c['past'] ? 'Sudah lewat' : 'Bukan hari kunjungan' }}</small>
                </div>
            @else
                <a class="{{ $cls }}" href="{{ $query(['date' => $c['iso']]) }}" @if ($on) aria-current="date" @endif>
                    <b>{{ $dayName[$c['date']->dayOfWeekIso] }} {{ $c['date']->format('d-m') }}</b>
                    <span class="sstock-day__load">{{ $c['n'] }}/{{ $cap }}</span>
                    <small>{{ $full ? 'Penuh' : ($cap - $c['n']).' slot kosong' }}</small>
                </a>
            @endif
        @endforeach
    </div>
    <p class="dl-note">Kunjungan hanya hari {{ $days }}, pukul {{ $window }}. Kapasitas {{ $cap }} AC per hari untuk setiap vendor. Angka di tiap hari = AC terjadwal / kapasitas.</p>

    <x-dl::divider label="AC yang perlu dibersihkan" />
    <form method="POST" action="{{ route('ac-schedule.store') }}" id="schedule-form">
        @csrf
        <input type="hidden" name="vendor_id" value="{{ $vendorId }}">
        <input type="hidden" name="date" value="{{ $selected?->toDateString() }}">
        <x-dl::form.row :cols="3">
            <x-dl::field.display label="Tanggal kunjungan terpilih" :value="raw($dateText ?? 'Pilih hari di kalender')" />
            <x-dl::field.display label="Sisa kapasitas hari itu" :value="raw($selected ? $left.' dari '.$cap.' AC' : '-')" />
            <x-dl::field.time name="time" label="Jam mulai" :value="old('time', $time)" hint="Bawaan jam awal kunjungan." />
        </x-dl::form.row>
        @if ($rows->isNotEmpty())
            <div class="dl-table-wrap dl-table--stack">
                <table class="dl-table is-striped">
                    <thead><tr>
                        <th style="width: 2.5rem">@if ($canWrite)<input type="checkbox" id="ac-all" aria-label="Pilih semua AC yang belum terjadwal">@endif</th>
                        <th>AC dan lokasi</th><th>Terakhir dicuci</th><th>Jatuh tempo</th><th>Pekerjaan</th><th>Status</th><th>Jadwal</th>
                    </tr></thead>
                    <tbody>
                        @foreach ($rows as $r)
                            @php($u = $r['unit'])
                            @php($o = $r['o'])
                            <tr>
                                <td data-label="Pilih">
                                    <input type="checkbox" name="units[]" value="{{ $u->id }}" aria-label="Pilih {{ $u->label() }}" @checked(in_array($u->id, $oldUnits)) @disabled($o['open'] || ! $canWrite)>
                                </td>
                                <td data-label="AC dan lokasi"><b>{{ $u->label() }}</b><br><small>{{ $u->room->area->name }} · {{ $u->room->name }} · {{ $u->typeText() }}</small></td>
                                <td data-label="Terakhir dicuci">{{ $o['last_wash']?->format('d-m-Y') ?? 'Belum tercatat' }}</td>
                                <td data-label="Jatuh tempo">{{ $o['due']->format('d-m-Y') }}<br><small>{{ $o['due_text'] }}</small></td>
                                <td data-label="Pekerjaan">{{ AcCare::JOBS[$o['job']] }}</td>
                                <td data-label="Status"><x-dl::badge :tone="$tone[$o['label']]" :label="$o['label']" /></td>
                                <td data-label="Jadwal">
                                    @if ($o['open'])
                                        @if ($canVisits)<a href="{{ route('ac-visits.show', $o['open']['order_id']) }}">{{ $o['open']['date']->format('d-m-Y') }}</a>@else{{ $o['open']['date']->format('d-m-Y') }}@endif
                                        <br><small>{{ $o['open']['no'] }} · {{ AcCare::ORDER_STATUS[$o['open']['status']] }}</small>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="dl-note">Tidak ada AC yang perlu dibersihkan{{ $areaId ? ' di area ini' : '' }}. Pilih "Semua AC aktif" untuk melihat semuanya.</p>
        @endif
        @if ($canWrite)
            <x-dl::action-bar align="start">
                <x-dl::button type="submit" icon="fa-calendar-plus" label="Jadwalkan yang dipilih" :disabled="! $selected" />
                <x-dl::button type="submit" variant="secondary" icon="fa-wand-magic-sparkles" label="Susun otomatis" form="auto-form" :disabled="! $selected" />
            </x-dl::action-bar>
        @endif
    </form>
    @if ($canWrite)
        <form method="POST" action="{{ route('ac-schedule.auto') }}" id="auto-form">
            @csrf
            <input type="hidden" name="vendor_id" value="{{ $vendorId }}">
            <input type="hidden" name="date" value="{{ $selected?->toDateString() }}">
        </form>
        <p class="dl-note">Susun otomatis menjadwalkan semua AC berstatus Terlambat atau Segera yang belum terjadwal, paling terlambat dulu, mulai dari tanggal terpilih, hari demi hari sesuai kapasitas dan hari kunjungan yang diizinkan. Hasilnya berstatus Draf.</p>
        <x-dl::action-form :action="route('ac-schedule.capacity')">
            <x-dl::form.row :cols="4">
                <x-dl::field.select name="capacity" label="Kapasitas harian vendor" :options="[2 => '2 AC per hari', 3 => '3 AC per hari'] + [$cap => $cap.' AC per hari']" :value="$cap" />
            </x-dl::form.row>
            <x-dl::action-bar align="start">
                <x-dl::button type="submit" variant="secondary" size="sm" icon="save" label="Simpan kapasitas" />
            </x-dl::action-bar>
        </x-dl::action-form>
    @endif
    <script>
        // Pilih semua AC yang belum terjadwal.
        (() => {
            const all = document.getElementById('ac-all');
            all && all.addEventListener('change', () => document.querySelectorAll('#schedule-form input[name="units[]"]:not(:disabled)').forEach(c => { c.checked = all.checked; }));
        })();
    </script>
    @endif
@endsection
