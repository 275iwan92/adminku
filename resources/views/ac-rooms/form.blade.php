{{-- REMARK
 Tujuan       : Form master Ruang AC (Single Form kind=master): area, kode ruang (kosong = otomatis dari nama, bisa diubah), nama, persegi di denah (persen: kiri, atas, lebar, tinggi), PIC (nama, WhatsApp, e-mail).
 Sumber hulu  : AcRoomController (room, areas).
 Input        : room, areas (id => nama).
 Output       : POST ac-rooms.store (baru) / PUT ac-rooms.update (ubah, Aktifkan, Nonaktifkan).
 Dipakai oleh : routes ac-rooms.create, ac-rooms.show.
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Ruang AC'])
@section('body')
    @php
        $isNew = ! $room->exists;
        $v = fn (string $k) => old($k, $room->{$k});
        $e = fn (string $k) => $errors->first($k) ?: null;
    @endphp
    @include('partials.flash')
    <x-dl::form :title="$isNew ? 'Ruang AC · Baru' : 'Ruang AC · '.raw($room->code)" :subtitle="$isNew ? null : raw($room->name)" panel="top" kind="master"
        :status="$isNew ? 'new' : ($room->is_active ? 'active' : 'inactive')" :back="route('ac-rooms.index')" :new-url="route('ac-rooms.create')"
        :action="$isNew ? route('ac-rooms.store') : route('ac-rooms.update', $room)" :hidden="$isNew ? [] : ['_method' => 'PUT']"
        :created-at="$room->created_at?->format('Y-m-d H:i:s')" :updated-at="$room->updated_at?->format('Y-m-d H:i:s')">
        <x-dl::form.row :cols="3">
            <x-dl::field.select name="area_id" label="Area / Gedung" :options="$areas" :value="$v('area_id')" required :error="$e('area_id')" />
            <x-dl::field.text name="name" label="Nama Ruang" :value="$v('name')" required maxlength="100" :error="$e('name')" hint="Mis. Ruang Meeting." />
            <x-dl::field.text name="code" label="Kode Ruang" :value="$v('code')" code :case-toggle="false" maxlength="20" :error="$e('code')" hint="Kosong = otomatis dari nama (huruf depan). Boleh diubah; unik." />
        </x-dl::form.row>
        <x-dl::form.row :cols="4">
            <x-dl::field.decimal name="x" label="Denah: Kiri" :decimals="1" unit="%" :value="$v('x')" required :error="$e('x')" hint="Jarak dari tepi kiri denah, 0 sampai 100." />
            <x-dl::field.decimal name="y" label="Denah: Atas" :decimals="1" unit="%" :value="$v('y')" required :error="$e('y')" hint="Jarak dari tepi atas denah." />
            <x-dl::field.decimal name="w" label="Denah: Lebar" :decimals="1" unit="%" :value="$v('w')" required :error="$e('w')" hint="Kiri + lebar tidak lebih dari 100." />
            <x-dl::field.decimal name="h" label="Denah: Tinggi" :decimals="1" unit="%" :value="$v('h')" required :error="$e('h')" hint="Atas + tinggi tidak lebih dari 100." />
        </x-dl::form.row>
        <x-dl::form.row :cols="3">
            <x-dl::field.text name="pic_name" label="PIC (Penanggung Jawab)" :value="$v('pic_name')" maxlength="100" :error="$e('pic_name')" />
            <x-dl::field.text name="pic_phone" label="WhatsApp PIC" :value="$v('pic_phone')" maxlength="30" :error="$e('pic_phone')" hint="Mis. 0812 3456 7890. Diubah otomatis ke 62... saat membuat tautan WhatsApp." />
            <x-dl::field.text name="pic_email" label="E-mail PIC" :value="$v('pic_email')" maxlength="150" :error="$e('pic_email')" />
        </x-dl::form.row>
    </x-dl::form>
@endsection
