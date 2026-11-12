{{-- REMARK
 Tujuan       : Form master Jenis Kendaraan (Single Form kind=master): kode (tetap setelah disimpan), nama, dan tabel Rencana Perawatan (pekerjaan, interval km, interval bulan; mana yang tercapai lebih dulu).
 Sumber hulu  : VehicleTypeController (type, plans).
 Input        : type, plans.
 Output       : POST vehicle-types.store (baru) / PUT vehicle-types.update (ubah nama + rencana, Aktifkan, Nonaktifkan).
 Dipakai oleh : routes vehicle-types.create, vehicle-types.show.
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Jenis Kendaraan'])
@section('body')
    @php
        $isNew = ! $type->exists;
        $v = fn (string $k) => old($k, $type->{$k});
        $e = fn (string $k) => $errors->first($k) ?: null;
    @endphp
    @include('partials.flash')
    <x-dl2::form :title="$isNew ? 'Jenis Kendaraan · Baru' : 'Jenis Kendaraan · '.raw($type->code)" :subtitle="$isNew ? null : raw($type->name)" panel="top" kind="master"
        :status="$isNew ? 'new' : ($type->is_active ? 'active' : 'inactive')" :back="route('vehicle-types.index')" :new-url="route('vehicle-types.create')"
        :action="$isNew ? route('vehicle-types.store') : route('vehicle-types.update', $type)" :method="$isNew ? 'post' : 'put'"
        :created-at="$type->created_at?->format('Y-m-d H:i:s')" :updated-at="$type->updated_at?->format('Y-m-d H:i:s')">
        <x-dl2::form.row :cols="3">
            <x-dl2::field.text name="code" label="Kode Jenis" :value="$v('code')" code :case-toggle="false" required :readonly="! $isNew" maxlength="20" :error="$e('code')" hint="Unik, mis. PICKUP. Tidak bisa diubah setelah disimpan." />
            <x-dl2::field.text name="name" label="Nama Jenis" :value="$v('name')" required maxlength="100" :error="$e('name')" />
        </x-dl2::form.row>
        @unless ($isNew)
            <x-dl2::form.row :cols="3">
                <x-dl2::field.display label="Kendaraan pemakai" :value="(string) $type->vehicles_count" />
            </x-dl2::form.row>
        @endunless
        <x-slot:detail>
            <x-dl2::lines name="plans" title="Rencana Perawatan" add-label="+ Tambah Pekerjaan" :rows="$plans" :min-rows="0" :row-state="true"
                :columns="[
                    ['key' => 'name', 'label' => 'Pekerjaan', 'type' => 'text', 'required' => true, 'placeholder' => 'Mis. Oli mesin'],
                    ['key' => 'interval_km', 'label' => 'Interval (km)', 'type' => 'number', 'width' => '180px'],
                    ['key' => 'interval_months', 'label' => 'Interval (bulan)', 'type' => 'number', 'width' => '180px'],
                ]" />
            <p class="ak-note">Isi interval km, interval bulan, atau keduanya; yang tercapai lebih dulu menentukan jadwal. Interval ini berlaku untuk semua kendaraan berjenis ini; tidak perlu diketik ulang saat mencatat servis. Mengganti nama pekerjaan dianggap pekerjaan baru.</p>
        </x-slot:detail>
    </x-dl2::form>
@endsection
