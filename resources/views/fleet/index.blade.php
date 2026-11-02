{{-- REMARK
 Tujuan       : Monitoring Kendaraan (GA, modul GVM): kotak ringkasan (kendaraan aktif, terlambat, segera, biaya servis tahun ini) lalu satu baris per kendaraan aktif: plat + nama, pajak tahunan (berlaku sampai + sisa hari),
                KIR (6 bulan; "Tidak wajib" bagi mobil penumpang), servis berikutnya (butir terdekat + sisa km / hari) dan status Aman / Segera / Terlambat. Kolom lain tersembunyi (jenis, odometer, STNK, asuransi).
 Sumber hulu  : FleetController@index (VehicleCare::overview).
 Input        : rows, active, late, soon, yearCost, year.
 Output       : kpi-row + x-dl::grid (Buka / klik 2x = Detail kendaraan); tombol input hanya bagi yang boleh menulis modulnya.
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
    <x-dl::kpi-row>
        <x-dl::stat-box label="Kendaraan aktif" :value="(string) $active" tone="accent" />
        <x-dl::stat-box label="Terlambat" :value="(string) $late" :tone="$late ? 'danger' : 'success'" sub="Dokumen atau servis sudah lewat" />
        <x-dl::stat-box label="Segera" :value="(string) $soon" :tone="$soon ? 'warn' : 'success'" :sub="'Dalam '.\App\Services\VehicleCare::soonDays().' hari atau '.number_format(\App\Services\VehicleCare::soonKm(), 0, ',', '.').' km'" />
        <x-dl::stat-box :label="'Biaya servis '.$year" :value="\App\Services\VehicleCare::rp($yearCost)" tone="neutral" />
    </x-dl::kpi-row>
    @if ($u->canWrite('GVD') || $u->canWrite('GVS') || $u->canWrite('GVO'))
        <x-dl::action-bar align="start">
        @if ($u->canWrite('GVD'))
            <x-dl::button class="sstock-btn-teal" variant="secondary" icon="fa-file-signature" label="Perpanjang Dokumen" :href="route('vehicle-docs.create')" />
        @endif
        @if ($u->canWrite('GVS'))
            <x-dl::button class="sstock-btn-coral" variant="secondary" icon="fa-screwdriver-wrench" label="Catat Servis" :href="route('vehicle-services.create')" />
        @endif
        @if ($u->canWrite('GVO'))
            <x-dl::button class="sstock-btn-amber" variant="secondary" icon="fa-gauge-high" label="Catat Odometer" :href="route('vehicle-odometers.create')" />
        @endif
        </x-dl::action-bar>
    @endif
    <x-dl::grid id="fleet" :rows="$rows" actions="worklist" :new-record="null" :open-url="url('fleet/{id}')" search="Cari plat, nama, jenis, status..."
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
