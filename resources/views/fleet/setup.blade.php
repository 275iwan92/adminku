{{-- REMARK
 Tujuan       : Setup Perawatan Kendaraan (GA, tombol Setup di Monitoring Kendaraan): ubin ke Master Kendaraan (GVK), Jenis Kendaraan beserta rencana perawatannya (GVT), dan Pengaturan (GAP); tiap ubin tampil hanya bila pengguna punya hak modulnya.
 Sumber hulu  : FleetController@setup; hak lewat User::canAccess.
 Input        : tidak ada.
 Output       : tautan ke vehicles.index, vehicle-types.index, ga-settings.edit.
 Dipakai oleh : tombol Setup di layar Monitoring Kendaraan (fleet.index).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Setup Perawatan Kendaraan'])
@section('body')
    @php
        $u = auth()->user();
        $tiles = [
            ['GVK', 'vehicles.index', 'fa-truck-pickup', 'Master Kendaraan', 'Plat, jenis, tahun, odometer awal'],
            ['GVT', 'vehicle-types.index', 'fa-list-check', 'Jenis Kendaraan', 'Jenis dan rencana perawatannya'],
            ['GAP', 'ga-settings.edit', 'fa-sliders', 'Pengaturan Kendaraan', 'Ambang Segera dan aturan perawatan'],
        ];
    @endphp
    @include('partials.flash')
    <x-dl::action-bar align="start">
        <x-dl::button class="sstock-btn-slate" variant="secondary" icon="fa-arrow-left" label="Kembali" :href="route('fleet.index')" />
    </x-dl::action-bar>
    <div class="sstock-setup">
        @foreach ($tiles as [$module, $route, $icon, $label, $text])
            @if ($u->canAccess($module))
                <a class="sstock-setup__tile" href="{{ route($route, $route === 'ga-settings.edit' ? ['dari' => 'fleet'] : []) }}">
                    <i class="fa-solid {{ $icon }}" aria-hidden="true"></i>
                    <b>{{ $label }}</b>
                    <small>{{ $text }}</small>
                </a>
            @endif
        @endforeach
    </div>
@endsection
