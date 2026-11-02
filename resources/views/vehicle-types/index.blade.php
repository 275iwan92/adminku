{{-- REMARK
 Tujuan       : Daftar master Jenis Kendaraan (Grid Master, modul GVT) beserta jumlah butir rencana perawatan dan kendaraan pemakainya.
 Sumber hulu  : VehicleTypeController@index.
 Input        : rows.
 Output       : x-dl::grid; klik 2x / Edit membuka form jenis; saklar Aktif / Nonaktif.
 Dipakai oleh : route vehicle-types.index (menu GA > Jenis Kendaraan).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Jenis Kendaraan'])
@section('body')
    @include('partials.flash')
    <div style="max-width: 820px">
    <x-dl::grid id="vehicle-types" :rows="$rows" actions="master" :new-url="route('vehicle-types.create')" :open-url="url('vehicle-types/{id}')" :toggle-url="url('vehicle-types/{id}/toggle')"
        search="Cari kode atau nama..."
        :phone-cards="['title' => 'name', 'sub' => 'code', 'meta' => ['plans', 'vehicles']]"
        :columns="[
            ['key' => 'name', 'label' => 'Jenis Kendaraan', 'type' => 'identity', 'code' => 'code', 'hue' => 'hue', 'box' => true],
            ['key' => 'plans', 'label' => 'Butir perawatan', 'type' => 'integer'],
            ['key' => 'vehicles', 'label' => 'Kendaraan', 'type' => 'integer'],
        ]"
        :stats="[['label' => 'Semua jenis'], ['label' => 'Nonaktif', 'where' => ['active' => false], 'tone' => 'warn']]" />
    </div>
@endsection
