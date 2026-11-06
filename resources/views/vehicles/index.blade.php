{{-- REMARK
 Tujuan       : Daftar master Kendaraan (Grid Master, modul GVK): plat + nama / model dalam kotak berwarna menurut jenis, jenis, tahun, status wajib KIR, odometer terakhir, no. rangka dan mesin (tersembunyi).
 Sumber hulu  : VehicleController@index.
 Input        : rows.
 Output       : x-dl::grid; klik 2x / Edit membuka form kendaraan.
 Dipakai oleh : route vehicles.index (menu GA > Master Kendaraan).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Master Kendaraan'])
@section('body')
    @include('partials.flash')
    @include('partials.back-to-hub', ['hub' => 'fleet'])
    <x-dl::grid id="vehicles" :rows="$rows" actions="edit" :new-url="route('vehicles.create')" :open-url="url('vehicles/{id}')" :toggle-url="url('vehicles/{id}/toggle')"
        search="Cari plat, nama, jenis..."
        :phone-cards="['title' => 'plate', 'sub' => 'name', 'meta' => ['type', 'kir', 'odometer']]"
        :columns="[
            ['key' => 'plate', 'label' => 'Kendaraan', 'type' => 'identity', 'code' => 'name', 'hue' => 'hue', 'box' => false],
            ['key' => 'type', 'label' => 'Jenis'],
            ['key' => 'year', 'label' => 'Tahun', 'type' => 'integer'],
            ['key' => 'kir', 'label' => 'KIR'],
            ['key' => 'odometer', 'label' => 'Odometer (km)', 'type' => 'integer'],
            ['key' => 'vin', 'label' => 'No. Rangka', 'visible' => false],
            ['key' => 'engine_no', 'label' => 'No. Mesin', 'visible' => false],
        ]"
        :stats="[['label' => 'Semua kendaraan'], ['label' => 'Nonaktif', 'where' => ['active' => false], 'tone' => 'warn']]" />
@endsection
