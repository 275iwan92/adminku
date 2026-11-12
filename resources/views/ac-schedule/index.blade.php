{{-- REMARK
 Tujuan       : Penjadwalan AC (GA, modul GAS), satu layar dengan isian Tipe Preventif atau Breakdown (Breakdown: semua AC aktif bisa dipilih, keluhan wajib, tingkat Mendesak boleh di luar kapasitas dan hari kunjungan dengan alasan; Preventif seperti semula): kotak ringkasan (total AC, terlambat dicuci, jatuh tempo dalam ambang, terjadwal minggu ini), kalender minggu Senin-Sabtu dengan isi / kapasitas per hari (klik hari = tanggal
                kunjungan terpilih; hari penuh bertanda), daftar AC yang perlu dibersihkan (centang, paling terlambat dulu, saring per area), tombol Jadwalkan yang dipilih, Susun otomatis, dan pemilih kapasitas harian.
 Sumber hulu  : AcScheduleController@index (type, vendors, vendorId, calendar, week, selected, cap, rows, pick, areas, areaId, all, time, days, window, summary, canWrite, canVisits, dateText, left).
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
        $breakdown = $type === 'breakdown';
        $e = fn (string $k) => $errors->first($k) ?: null;
    @endphp
    @include('partials.flash')
    <x-dl2::action-bar align="start">
        @if (auth()->user()->canAccess('GAM'))
            <x-dl2::button class="sstock-btn-slate" variant="secondary" icon="fa-arrow-left" label="Kembali" :href="route('ac-care.index')" />
        @endif
        <x-dl2::button class="sstock-btn-teal" icon="fa-calendar-check" label="Jadwal" :href="route('ac-schedule.index')" />
        @if ($canVisits)
            <x-dl2::button class="sstock-btn-coral" variant="secondary" icon="fa-truck-fast" label="Kunjungan dan Catat hasil" :href="route('ac-visits.index')" />
        @endif
    </x-dl2::action-bar>
    <x-dl2::kpi-row>
        <x-dl2::stat-box label="Total AC" :value="(string) $summary['total']" tone="accent" sub="AC aktif" />
        <x-dl2::stat-box label="Terlambat dicuci" :value="(string) $summary['late']" :tone="$summary['late'] ? 'danger' : 'success'" sub="Jatuh tempo sudah lewat" />
        <x-dl2::stat-box label="Jatuh tempo" :value="(string) $summary['soon']" :tone="$summary['soon'] ? 'warn' : 'success'" :sub="'Dalam '.AcCare::soonDays().' hari'" />
        <x-dl2::stat-box label="Terjadwal minggu ini" :value="(string) $summary['week']" tone="neutral" sub="AC pada kunjungan minggu ini" />
    </x-dl2::kpi-row>

    @if (! $vendors)
        <x-dl2::empty-state title="Belum ada vendor AC aktif" message="Tambahkan vendor di menu Vendor AC lebih dulu." icon="fa-handshake" />
    @else
    <x-dl2::action-form method="get" :action="route('ac-schedule.index')" class="ak-report-filter">
        <input type="hidden" name="week" value="{{ $week->toDateString() }}">
        @if ($selected)
            <input type="hidden" name="date" value="{{ $selected->toDateString() }}">
        @endif
        <x-dl2::form.row :cols="4">
            <x-dl2::field.select name="type" label="Tipe" :options="['preventive' => 'Preventif (jadwal rutin)', 'breakdown' => 'Breakdown (AC rusak)']" :value="$type" />
            <x-dl2::field.select name="vendor" label="Vendor" :options="$vendors" :value="$vendorId" />
            <x-dl2::field.select name="area" label="Area" :options="['' => 'Semua area'] + $areas" :value="$areaId ?: ''" />
            @if (! $breakdown)
                <x-dl2::field.select name="show" label="Tampilkan" :options="['perlu' => 'AC yang perlu dibersihkan', 'semua' => 'Semua AC aktif']" :value="$all ? 'semua' : 'perlu'" />
            @endif
        </x-dl2::form.row>
        <x-dl2::action-bar>
            <x-dl2::button class="sstock-btn-teal" type="submit" icon="fa-filter" label="Terapkan" size="sm" />
        </x-dl2::action-bar>
    </x-dl2::action-form>

    <x-dl2::divider :label="'Kalender kunjungan · '.$week->format('d-m-Y').' sampai '.$week->copy()->addDays(5)->format('d-m-Y')" />
    <x-dl2::action-bar align="start">
        <x-dl2::button class="sstock-btn-slate" variant="secondary" size="sm" icon="fa-chevron-left" label="Minggu sebelumnya" :href="$query(['week' => $week->copy()->subWeek()->toDateString(), 'date' => null])" />
        <x-dl2::button class="sstock-btn-slate" variant="secondary" size="sm" label="Minggu ini" :href="$query(['week' => null, 'date' => null])" />
        <x-dl2::button class="sstock-btn-slate" variant="secondary" size="sm" icon="fa-chevron-right" label="Minggu berikutnya" :href="$query(['week' => $week->copy()->addWeek()->toDateString(), 'date' => null])" />
    </x-dl2::action-bar>
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
    <p class="ak-note">Kunjungan hanya hari {{ $days }}, pukul {{ $window }}. Kapasitas {{ $cap }} AC per hari untuk setiap vendor. Angka di tiap hari = AC terjadwal / kapasitas.</p>

    <x-dl2::divider :label="$breakdown ? 'AC yang rusak (pilih semua AC yang perlu diperbaiki)' : 'AC yang perlu dibersihkan'" />
    <form method="POST" action="{{ route('ac-schedule.store') }}" id="schedule-form">
        @csrf
        <input type="hidden" name="vendor_id" value="{{ $vendorId }}">
        <input type="hidden" name="type" value="{{ $type }}">
        @if ($breakdown)
            <x-dl2::form.row :cols="3">
                <x-dl2::field.date name="date" label="Tanggal kunjungan" :value="old('date', ($selected ?? today())->toDateString())" required :error="$e('date')" hint="Tingkat Mendesak boleh hari apa saja, termasuk hari ini." />
                <x-dl2::field.time name="time" label="Jam mulai" :value="old('time', $time)" :error="$e('time')" hint="Dalam jendela {{ $window }}." />
                <x-dl2::field.select name="priority" label="Tingkat" :options="\App\Models\AcOrder::PRIORITIES" :value="old('priority', 'normal')" />
            </x-dl2::form.row>
            <x-dl2::form.row :cols="2">
                <x-dl2::field.text name="complaint" label="Keluhan" :value="old('complaint')" required maxlength="255" :error="$e('complaint')" hint="Mis. Tidak dingin, menetes air." />
                <x-dl2::field.text name="override_reason" label="Alasan mendesak" :value="old('override_reason')" maxlength="255" :error="$e('override_reason')" hint="Wajib bila Mendesak: kunjungan boleh melampaui kapasitas harian dan hari kunjungan." />
            </x-dl2::form.row>
        @else
            <input type="hidden" name="date" value="{{ $selected?->toDateString() }}">
            <x-dl2::form.row :cols="3">
                <x-dl2::field.display label="Tanggal kunjungan terpilih" :value="raw($dateText ?? 'Pilih hari di kalender')" />
                <x-dl2::field.display label="Sisa kapasitas hari itu" :value="raw($selected ? $left.' dari '.$cap.' AC' : '-')" />
                <x-dl2::field.time name="time" label="Jam mulai" :value="old('time', $time)" hint="Bawaan jam awal kunjungan." />
            </x-dl2::form.row>
        @endif
        @if ($rows->isNotEmpty())
            <div class="dl2-table-responsive ak-stack">
                <table class="dl2-table is-striped">
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
                                    <input type="checkbox" name="units[]" value="{{ $u->id }}" aria-label="Pilih {{ $u->label() }}" @checked(in_array($u->id, $oldUnits) || $u->id === $pick) @disabled($o['open'] || ! $canWrite)>
                                </td>
                                <td data-label="AC dan lokasi"><b>{{ $u->label() }}</b><br><small>{{ $u->room->area->name }} · {{ $u->room->name }} · {{ $u->typeText() }}</small></td>
                                <td data-label="Terakhir dicuci">{{ $o['last_wash']?->format('d-m-Y') ?? 'Belum tercatat' }}</td>
                                <td data-label="Jatuh tempo">{{ $o['due']->format('d-m-Y') }}<br><small>{{ $o['due_text'] }}</small></td>
                                <td data-label="Pekerjaan">{{ $breakdown ? AcCare::JOBS['fix'] : AcCare::JOBS[$o['job']] }}</td>
                                <td data-label="Status"><x-dl2::badge :tone="$tone[$o['label']]" :label="$o['label']" /></td>
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
            <p class="ak-note">Tidak ada AC yang perlu dibersihkan{{ $areaId ? ' di area ini' : '' }}. Pilih "Semua AC aktif" untuk melihat semuanya.</p>
        @endif
        @if ($canWrite)
            <x-dl2::action-bar align="start">
                <x-dl2::button class="sstock-btn-teal" type="submit" icon="fa-calendar-plus" :label="$breakdown ? 'Jadwalkan perbaikan' : 'Jadwalkan yang dipilih'" :disabled="! $breakdown && ! $selected" />
                @if (! $breakdown)
                    <x-dl2::button class="sstock-btn-teal" type="submit" variant="secondary" icon="fa-wand-magic-sparkles" label="Susun otomatis" form="auto-form" :disabled="! $selected" />
                @endif
            </x-dl2::action-bar>
        @endif
    </form>
    @if ($canWrite)
        <form method="POST" action="{{ route('ac-schedule.auto') }}" id="auto-form">
            @csrf
            <input type="hidden" name="vendor_id" value="{{ $vendorId }}">
            <input type="hidden" name="date" value="{{ $selected?->toDateString() }}">
        </form>
        @if (! $breakdown)
            <p class="ak-note">Susun otomatis menjadwalkan semua AC berstatus Terlambat atau Segera yang belum terjadwal, paling terlambat dulu, mulai dari tanggal terpilih, hari demi hari sesuai kapasitas dan hari kunjungan yang diizinkan. Hasilnya berstatus Draf.</p>
        @endif
        <x-dl2::action-form :action="route('ac-schedule.capacity')">
            <x-dl2::form.row :cols="4">
                <x-dl2::field.select name="capacity" label="Kapasitas harian vendor" :options="[2 => '2 AC per hari', 3 => '3 AC per hari'] + [$cap => $cap.' AC per hari']" :value="$cap" />
            </x-dl2::form.row>
            <x-dl2::action-bar align="start">
                <x-dl2::button class="sstock-btn-teal" type="submit" variant="secondary" size="sm" icon="save" label="Simpan kapasitas" />
            </x-dl2::action-bar>
        </x-dl2::action-form>
    @endif
    <script>
        // Pilih semua AC yang belum terjadwal.
        window.akReady(() => {
            const all = document.getElementById('ac-all');
            all && all.addEventListener('change', () => document.querySelectorAll('#schedule-form input[name="units[]"]:not(:disabled)').forEach(c => { c.checked = all.checked; }));
        });
    </script>
    @endif
@endsection
