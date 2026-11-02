{{-- REMARK
 Tujuan       : Form Catat Odometer (cepat; GA, modul GVO; Single Form kind=master status baru): kendaraan, tanggal (bawaan hari ini), kilometer (bawaan = odometer terakhir), dicatat oleh (bawaan nama pengguna), foto bukti opsional. Tanpa OCR.
 Sumber hulu  : VehicleOdometerController@create (vehicles, selected, lastKm, back).
 Input        : vehicles (id => label), selected, lastKm (kendaraan => odometer terakhir), back.
 Output       : POST vehicle-odometers.store (multipart).
 Dipakai oleh : route vehicle-odometers.create (menu GA > Catat Odometer; tombol di Detail kendaraan).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Catat Odometer'])
@section('body')
    @php
        $e = fn (string $k) => $errors->first($k) ?: null;
        $vehicleId = is_scalar(old('vehicle_id', $selected)) ? old('vehicle_id', $selected) : $selected;
    @endphp
    @include('partials.flash')
    <x-dl::form title="Catat Odometer" subtitle="Cukup pilih kendaraan, isi kilometer yang tertera, dan (dianjurkan) lampirkan foto panelnya" panel="top" kind="master" status="new"
        :back="$back" :action="route('vehicle-odometers.store')" enctype="multipart/form-data">
        <x-dl::form.row :cols="3">
            <x-dl::field.select name="vehicle_id" label="Kendaraan" :options="$vehicles" :value="$vehicleId" required :error="$e('vehicle_id')" />
            <x-dl::field.date name="read_at" label="Tanggal" :value="old('read_at', today()->format('Y-m-d'))" required :error="$e('read_at')" />
            <x-dl::field.decimal name="km" label="Kilometer" :decimals="0" unit="km" :value="old('km', $lastKm[$vehicleId] ?? 0)" required :error="$e('km')" hint="Bawaan: odometer terakhir. Tidak boleh lebih kecil dari catatan terakhir." />
        </x-dl::form.row>
        <x-dl::form.row :cols="3">
            <x-dl::field.text name="recorded_by" label="Dicatat Oleh" :value="old('recorded_by', auth()->user()->name)" maxlength="100" :error="$e('recorded_by')" />
        </x-dl::form.row>
        <x-dl::form.row>
            <x-dl::field.upload name="photo" label="Foto Odometer" accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf" :error="$e('photo')" hint="Foto panel odometer sebagai bukti (dianjurkan, tidak wajib). Angkanya tidak dibaca otomatis; ketik kilometernya di atas. JPG, PNG, atau PDF, paling besar 5 MB." />
        </x-dl::form.row>
    </x-dl::form>
    <script>
        // Kilometer bawaan mengikuti odometer terakhir kendaraan yang dipilih.
        (() => {
            const lastKm = @json($lastKm);
            const form = document.querySelector('form[action="{{ route('vehicle-odometers.store') }}"]');
            form.addEventListener('change', e => {
                if (e.target.name === 'vehicle_id') window.Devlib.h.setField(form, 'km', lastKm[e.target.value] ?? 0);
            });
        })();
    </script>
@endsection
