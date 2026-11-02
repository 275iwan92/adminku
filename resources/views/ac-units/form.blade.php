{{-- REMARK
 Tujuan       : Form master Unit AC (Single Form kind=master): kode aset permanen (hanya-baca setelah disimpan), ruang (kode ruang ikut berubah bila AC dipindah), merek, tipe, PK, tanggal pasang,
                posisi titik di denah (persen; kosong = otomatis di tengah ruang), cuci rutin dan servis besar terakhir sebelum tercatat di sistem, catatan.
 Sumber hulu  : AcUnitController (unit, rooms).
 Input        : unit, rooms (id => "KODE · nama (area)").
 Output       : POST ac-units.store (baru) / PUT ac-units.update (ubah, Aktifkan, Nonaktifkan).
 Dipakai oleh : routes ac-units.create, ac-units.show.
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Unit AC'])
@section('body')
    @php
        $isNew = ! $unit->exists;
        $v = fn (string $k) => old($k, $unit->{$k});
        $d = fn (string $k) => old($k, $unit->{$k}?->format('Y-m-d'));
        $e = fn (string $k) => $errors->first($k) ?: null;
    @endphp
    @include('partials.flash')
    <x-dl::form :title="$isNew ? 'Unit AC · Baru' : 'Unit AC · '.raw($unit->asset_code)" :subtitle="$isNew ? null : raw($unit->typeText())" panel="top" kind="master"
        :status="$isNew ? 'new' : ($unit->is_active ? 'active' : 'inactive')" :back="route('ac-units.index')" :new-url="route('ac-units.create')"
        :action="$isNew ? route('ac-units.store') : route('ac-units.update', $unit)" :hidden="$isNew ? [] : ['_method' => 'PUT']"
        :created-at="$unit->created_at?->format('Y-m-d H:i:s')" :updated-at="$unit->updated_at?->format('Y-m-d H:i:s')">
        <x-dl::form.row :cols="3">
            @if ($isNew)
                <x-dl::field.text name="asset_code" label="Kode Aset" :value="$v('asset_code')" code :case-toggle="false" required maxlength="20" :error="$e('asset_code')" hint="Unik, ditempel sebagai stiker di unit. Tidak berubah walau AC dipindah." />
            @else
                <x-dl::field.display label="Kode Aset" :value="raw($unit->asset_code)" />
            @endif
            <x-dl::field.select name="room_id" label="Ruang" :options="$rooms" :value="$v('room_id')" required :error="$e('room_id')" hint="Dipindah ke ruang lain: kode ruang berubah, kode aset tetap." />
        </x-dl::form.row>
        <x-dl::form.row :cols="4">
            <x-dl::field.text name="brand" label="Merek" :value="$v('brand')" required maxlength="60" :error="$e('brand')" hint="Mis. Daikin, Panasonic, LG." />
            <x-dl::field.text name="model" label="Tipe" :value="$v('model')" maxlength="80" :error="$e('model')" />
            <x-dl::field.decimal name="capacity_pk" label="Kapasitas" :decimals="1" unit="PK" :value="$v('capacity_pk')" required :error="$e('capacity_pk')" />
            <x-dl::field.date name="installed_at" label="Tanggal Pasang" :value="$d('installed_at')" :error="$e('installed_at')" />
        </x-dl::form.row>
        <x-dl::form.row :cols="4">
            <x-dl::field.date name="last_wash_at" label="Cuci Rutin Terakhir (awal)" :value="$d('last_wash_at')" :error="$e('last_wash_at')" hint="Sebelum tercatat di sistem. Dasar jatuh tempo sampai ada riwayat kunjungan." />
            <x-dl::field.date name="last_major_at" label="Servis Besar Terakhir (awal)" :value="$d('last_major_at')" :error="$e('last_major_at')" hint="Kosong = dihitung dari tanggal pasang." />
            <x-dl::field.decimal name="pos_x" label="Denah: Titik Kiri" :decimals="1" unit="%" :value="$v('pos_x')" :error="$e('pos_x')" hint="Kosong = otomatis di tengah ruang." />
            <x-dl::field.decimal name="pos_y" label="Denah: Titik Atas" :decimals="1" unit="%" :value="$v('pos_y')" :error="$e('pos_y')" />
        </x-dl::form.row>
        <x-dl::form.row>
            <x-dl::field.text name="note" label="Catatan" :value="$v('note')" maxlength="255" :error="$e('note')" />
        </x-dl::form.row>
    </x-dl::form>
@endsection
