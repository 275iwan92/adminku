{{-- REMARK
 Tujuan       : Monitoring Kendaraan (GA, modul GVM): kotak ringkasan (kendaraan aktif, terlambat, segera, biaya servis tahun ini) lalu satu baris per kendaraan aktif: plat + nama, pajak tahunan (berlaku sampai + sisa hari),
                KIR (6 bulan; "Tidak wajib" bagi mobil penumpang), servis berikutnya (butir terdekat + sisa km / hari) dan status Aman / Segera / Terlambat. Kolom lain tersembunyi (jenis, odometer, STNK, asuransi).
 Sumber hulu  : FleetController@index (VehicleCare::overview).
 Input        : rows, active, late, soon, yearCost, year.
 Output       : kpi-row + x-dl2::grid (Buka / klik 2x = Detail kendaraan); satu tombol Setup (bagi yang punya hak GVK / GVT / GAP); input harian (Perpanjang Dokumen, Catat Servis, Catat Odometer) lewat tombol di Detail kendaraan.
 Dipakai oleh : route fleet.index (menu GA > Monitoring Kendaraan).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Monitoring Kendaraan'])
@section('body')
    @php
        $tones = \App\Services\VehicleCare::TONES;
        $u = auth()->user();
    @endphp
    @include('partials.flash')
    <x-dl2::kpi-row>
        <x-dl2::stat-box label="Kendaraan aktif" :value="(string) $active" tone="accent" />
        <x-dl2::stat-box label="Terlambat" :value="(string) $late" :tone="$late ? 'danger' : 'success'" sub="Dokumen atau servis sudah lewat" />
        <x-dl2::stat-box label="Segera" :value="(string) $soon" :tone="$soon ? 'warn' : 'success'" :sub="'Dalam '.\App\Services\VehicleCare::soonDays().' hari atau '.number_format(\App\Services\VehicleCare::soonKm(), 0, ',', '.').' km'" />
        <x-dl2::stat-box :label="'Biaya servis '.$year" :value="\App\Services\VehicleCare::rp($yearCost)" tone="neutral" />
    </x-dl2::kpi-row>
    @if ($u->canAccess('GVK') || $u->canAccess('GVT') || $u->canAccess('GAP'))
        <x-dl2::action-bar align="start">
            <x-dl2::button class="sstock-btn-slate" variant="secondary" icon="fa-gear" label="Setup" :href="route('fleet.setup')" />
        </x-dl2::action-bar>
    @endif
    <x-dl2::grid id="fleet" :rows="$rows" actions="worklist" :new-record="null" :open-url="url('fleet/{id}')" search="Cari plat, nama, jenis, status..."
        :columns="[
            ['key' => 'plate', 'label' => 'Kendaraan', 'type' => 'identity', 'code' => 'name', 'avatar' => false],
            ['key' => 'tax', 'label' => 'Pajak tahunan'],
            ['key' => 'kir', 'label' => 'KIR'],
            ['key' => 'service', 'label' => 'Servis berikutnya'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => $tones],
            ['key' => 'type', 'label' => 'Jenis', 'visible' => false],
            ['key' => 'odometer', 'label' => 'Odometer (km)', 'type' => 'integer', 'visible' => false],
            ['key' => 'stnk', 'label' => 'STNK dan plat', 'visible' => false],
            ['key' => 'insurance', 'label' => 'Asuransi', 'visible' => false],
        ]"
        :phone-cards="['title' => 'plate', 'sub' => 'name', 'badge' => 'status', 'tones' => $tones, 'meta' => ['tax', 'kir', 'service']]" />
@endsection
