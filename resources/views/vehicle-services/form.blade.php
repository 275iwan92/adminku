{{-- REMARK
 Tujuan       : Form Catat Servis (GA, modul GVS; Single Form kind=master status baru): kendaraan, tanggal servis (bawaan hari ini), odometer (bawaan = odometer terakhir kendaraan), pekerjaan yang dikerjakan (centang dari jadwal jenis kendaraan itu),
                bengkel, biaya total, catatan. Jadwal berikutnya dihitung otomatis. Pengambilan oli / filter dari gudang tetap manual (modul ini tidak menyentuh stok).
 Sumber hulu  : VehicleServiceController@create (vehicles, selected, typeOf, lastKm, plans, back).
 Input        : vehicles (id => label), selected, typeOf (kendaraan => jenis), lastKm (kendaraan => odometer terakhir), plans (jenis => [butir => teks]), back.
 Output       : POST vehicle-services.store.
 Dipakai oleh : route vehicle-services.create (menu GA > Catat Servis Kendaraan; tombol di Detail kendaraan).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Catat Servis Kendaraan'])
@section('body')
    @php
        $e = fn (string $k) => $errors->first($k) ?: null;
        $vehicleId = is_scalar(old('vehicle_id', $selected)) ? old('vehicle_id', $selected) : $selected;
        $typeId = $typeOf[$vehicleId] ?? null;
    @endphp
    @include('partials.flash')
    <x-dl::form title="Catat Servis Kendaraan" subtitle="Jadwal servis berikutnya dihitung otomatis dari odometer dan tanggal servis" panel="top" kind="master" status="new" :back="$back" :action="route('vehicle-services.store')">
        <x-dl::form.row :cols="3">
            <x-dl::field.select name="vehicle_id" label="Kendaraan" :options="$vehicles" :value="$vehicleId" required :error="$e('vehicle_id')" />
            <x-dl::field.date name="serviced_at" label="Tanggal Servis" :value="old('serviced_at', today()->format('Y-m-d'))" required :error="$e('serviced_at')" />
            <x-dl::field.decimal name="odometer_km" label="Odometer (km)" :decimals="0" unit="km" :value="old('odometer_km', $lastKm[$vehicleId] ?? 0)" required :error="$e('odometer_km')" hint="Bawaan: odometer terakhir kendaraan. Tidak boleh lebih kecil dari catatan terakhir." />
        </x-dl::form.row>
        @foreach ($plans as $planType => $options)
            <div data-jobs-type="{{ $planType }}" @if ((string) $planType !== (string) $typeId) hidden @endif>
                <x-dl::form.row>
                    @if ($options)
                        <x-dl::field.checkboxes name="jobs" label="Pekerjaan yang Dikerjakan" :options="$options" :value="old('jobs', [])" :disabled="(string) $planType !== (string) $typeId" :error="(string) $planType === (string) $typeId ? ($e('jobs') ?: $e('jobs.0')) : null" hint="Centang dari jadwal perawatan kendaraan ini. Jadwal pekerjaan yang dicentang dihitung ulang dari servis ini." />
                    @else
                        <p class="dl-note">Jenis kendaraan ini belum punya rencana perawatan (isi di Jenis Kendaraan). Tuliskan pekerjaannya di Catatan.</p>
                    @endif
                </x-dl::form.row>
            </div>
        @endforeach
        <x-dl::form.row :cols="3">
            <x-dl::field.text name="workshop" label="Bengkel" :value="old('workshop')" maxlength="100" :error="$e('workshop')" />
            <x-dl::field.decimal name="cost" label="Biaya Total (IDR)" :decimals="2" :value="old('cost', 0)" required :error="$e('cost')" />
        </x-dl::form.row>
        <x-dl::form.row>
            <x-dl::field.text name="note" label="Catatan" :value="old('note')" maxlength="255" :error="$e('note')" hint="Wajib bila tidak ada pekerjaan yang dicentang (servis di luar jadwal)." />
        </x-dl::form.row>
    </x-dl::form>
    <script>
        // Daftar pekerjaan dan odometer bawaan mengikuti kendaraan yang dipilih; pekerjaan jenis lain disembunyikan dan dinonaktifkan supaya tidak terkirim.
        (() => {
            const typeOf = @json($typeOf), lastKm = @json($lastKm);
            const form = document.querySelector('form[action="{{ route('vehicle-services.store') }}"]');
            form.addEventListener('change', e => {
                if (e.target.name !== 'vehicle_id') return;
                form.querySelectorAll('[data-jobs-type]').forEach(g => {
                    const on = g.dataset.jobsType === String(typeOf[e.target.value]);
                    g.hidden = !on;
                    g.querySelectorAll('input').forEach(i => { i.disabled = !on; if (!on) i.checked = false; });
                });
                window.Devlib.h.setField(form, 'odometer_km', lastKm[e.target.value] ?? 0);
            });
        })();
    </script>
@endsection
