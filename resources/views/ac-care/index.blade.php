{{-- REMARK
 Tujuan       : Monitoring AC (GA, modul GAM; menu GA > Perawatan AC), meniru Monitoring Kendaraan: kotak ringkasan (AC aktif, Preventif terlambat, Preventif segera, Kerusakan terbuka, kunjungan terdekat), tombol Setup dan Penjadwalan,
                lalu satu baris per AC aktif: kode aset + ruang, area, cuci rutin berikutnya (+ sisa hari), servis besar berikutnya, kerusakan 12 bulan, status Aman / Segera / Terlambat / Rusak (breakdown terbuka).
 Sumber hulu  : AcCareController@index (rows, active, late, soon, broken, next).
 Input        : lihat sumber hulu.
 Output       : kpi-row + x-dl::grid (Buka / klik 2x = Denah AC dengan AC itu terpilih); tombol hanya bagi yang punya hak modulnya.
 Dipakai oleh : route ac-care.index (menu GA > Perawatan AC).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Perawatan AC'])
@section('body')
    @php
        use App\Services\AcCare;
        $tones = AcCare::TONES;
        $u = auth()->user();
        $setup = collect(['GAD', 'GAK', 'GAA', 'GAR', 'GAE', 'GAP'])->contains(fn ($m) => $u->canAccess($m));
        $schedule = $u->canAccess('GAS') || $u->canAccess('GAV');
    @endphp
    @include('partials.flash')
    <x-dl::kpi-row>
        <x-dl::stat-box label="AC aktif" :value="(string) $active" tone="accent" />
        <x-dl::stat-box label="Terlambat" :value="(string) $late" :tone="$late ? 'danger' : 'success'" sub="Cuci atau servis besar sudah lewat" />
        <x-dl::stat-box label="Segera" :value="(string) $soon" :tone="$soon ? 'warn' : 'success'" :sub="'Dalam '.AcCare::soonDays().' hari'" />
        <x-dl::stat-box label="Kerusakan terbuka" :value="(string) $broken" :tone="$broken ? 'danger' : 'success'" sub="AC rusak belum selesai ditangani" />
    </x-dl::kpi-row>
    @if ($setup || $schedule)
        <x-dl::action-bar align="start">
        @if ($setup)
            <x-dl::button class="sstock-btn-slate" variant="secondary" icon="fa-gear" label="Setup" :href="route('ac-setup.index')" />
        @endif
        @if ($schedule)
            <x-dl::button class="sstock-btn-teal" variant="secondary" icon="fa-calendar-check" label="Penjadwalan" :href="route($u->canAccess('GAS') ? 'ac-schedule.index' : 'ac-visits.index')" />
        @endif
        </x-dl::action-bar>
    @endif
    <p class="dl-note">{{ $next ? 'Kunjungan terdekat: '.\App\Services\AcMessage::dateText($next->visit_date).' · '.$next->no.' · '.$next->typeText().' · '.AcCare::ORDER_STATUS[$next->status].'.' : 'Belum ada kunjungan terjadwal.' }}</p>
    <x-dl::grid id="ac-care" :rows="$rows" actions="worklist" :new-record="null" :open-url="$u->canAccess('GAD') ? url('ac-plan?unit={id}') : null" search="Cari kode aset, ruang, area, status..."
        :columns="[
            ['key' => 'code', 'label' => 'AC', 'type' => 'identity', 'code' => 'name', 'avatar' => false],
            ['key' => 'area', 'label' => 'Area'],
            ['key' => 'wash', 'label' => 'Cuci berikutnya'],
            ['key' => 'major', 'label' => 'Servis besar'],
            ['key' => 'breakdown', 'label' => 'Kerusakan 12 bln', 'type' => 'integer'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => $tones],
            ['key' => 'room', 'label' => 'Ruang', 'visible' => false],
        ]"
        :phone-cards="['title' => 'code', 'sub' => 'name', 'badge' => 'status', 'tones' => $tones, 'meta' => ['wash', 'major']]" />
@endsection
