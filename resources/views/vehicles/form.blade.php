{{-- REMARK
 Tujuan       : Form master Kendaraan (Single Form kind=master): plat (= kode), nama / model, jenis, tahun, no. rangka / mesin, wajib KIR, odometer awal (hanya saat baru; sesudahnya hanya-baca) dan catatan.
 Sumber hulu  : VehicleController (vehicle, types).
 Input        : vehicle, types.
 Output       : POST vehicles.store (baru) / PUT vehicles.update (ubah, Aktifkan, Nonaktifkan).
 Dipakai oleh : routes vehicles.create, vehicles.show.
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Kendaraan'])
@section('body')
    @php
        $isNew = ! $vehicle->exists;
        $v = fn (string $k) => old($k, $vehicle->{$k});
        $e = fn (string $k) => $errors->first($k) ?: null;
        $kir = session()->hasOldInput() ? (bool) old('kir_required') : $vehicle->kir_required;
    @endphp
    @include('partials.flash')
    <x-dl2::form :title="$isNew ? 'Kendaraan · Baru' : 'Kendaraan · '.raw($vehicle->plate)" :subtitle="$isNew ? null : raw($vehicle->name)" panel="top" kind="master"
        :status="$isNew ? 'new' : ($vehicle->is_active ? 'active' : 'inactive')" :back="route('vehicles.index')" :new-url="route('vehicles.create')"
        :action="$isNew ? route('vehicles.store') : route('vehicles.update', $vehicle)" :method="$isNew ? 'post' : 'put'"
        :created-at="$vehicle->created_at?->format('Y-m-d H:i:s')" :updated-at="$vehicle->updated_at?->format('Y-m-d H:i:s')">
        <x-dl2::form.row :cols="3">
            <x-dl2::field.text name="plate" label="Plat Nomor" :value="$v('plate')" code :case-toggle="false" required maxlength="20" :error="$e('plate')" hint="Unik. Plat nomor menjadi kode kendaraan, mis. N 1234 AB." />
            <x-dl2::field.text name="name" label="Nama / Model" :value="$v('name')" required maxlength="100" :error="$e('name')" hint="Mis. Daihatsu Gran Max Pickup." />
            <x-dl2::field.select name="vehicle_type_id" label="Jenis Kendaraan" :options="$types" :value="$v('vehicle_type_id')" required :error="$e('vehicle_type_id')" hint="Menentukan rencana perawatan (oli, filter, ban, dst.) yang dipantau." />
        </x-dl2::form.row>
        <x-dl2::form.row :cols="3">
            <x-dl2::field.text name="year" label="Tahun" :value="$v('year')" maxlength="4" :error="$e('year')" />
            <x-dl2::field.text name="vin" label="No. Rangka" :value="$v('vin')" maxlength="40" :error="$e('vin')" />
            <x-dl2::field.text name="engine_no" label="No. Mesin" :value="$v('engine_no')" maxlength="40" :error="$e('engine_no')" />
        </x-dl2::form.row>
        <x-dl2::form.row :cols="3">
            <x-dl2::field.toggle name="kir_required" label="Wajib KIR" on-label="Ya" off-label="Tidak" :value="$kir" hint="Ya untuk small truck / kendaraan angkutan barang. Mobil penumpang pribadi tidak wajib KIR." />
            @if ($isNew)
                <x-dl2::field.decimal name="odometer_km" label="Odometer Awal" :decimals="0" unit="km" :value="$v('odometer_km') ?? 0" :error="$e('odometer_km')" hint="Kilometer yang tertera sekarang. Sesudah disimpan, odometer hanya berubah lewat Catat Odometer atau Catat Servis." />
            @else
                <x-dl2::field.display label="Odometer Terakhir" :value="raw(\Devlib2\Locale::number($vehicle->odometer_km, 0).' km'.($vehicle->odometer_at ? ' · '.$vehicle->odometer_at->format('d-m-Y') : ''))" />
            @endif
        </x-dl2::form.row>
        <x-dl2::form.row>
            <x-dl2::field.text name="note" label="Catatan" :value="$v('note')" maxlength="255" :error="$e('note')" />
        </x-dl2::form.row>
    </x-dl2::form>
@endsection
