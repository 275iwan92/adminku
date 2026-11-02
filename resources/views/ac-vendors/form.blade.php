{{-- REMARK
 Tujuan       : Form master Vendor AC (Single Form kind=master): kode (otomatis VND-nnn, boleh diubah), nama, kontak, nomor WhatsApp dan e-mail (dipakai pemberitahuan kunjungan), catatan.
 Sumber hulu  : AcVendorController (vendor).
 Input        : vendor.
 Output       : POST ac-vendors.store (baru) / PUT ac-vendors.update (ubah, Aktifkan, Nonaktifkan).
 Dipakai oleh : routes ac-vendors.create, ac-vendors.show.
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Vendor AC'])
@section('body')
    @php
        $isNew = ! $vendor->exists;
        $v = fn (string $k) => old($k, $vendor->{$k});
        $e = fn (string $k) => $errors->first($k) ?: null;
    @endphp
    @include('partials.flash')
    <x-dl::form :title="$isNew ? 'Vendor AC · Baru' : 'Vendor AC · '.raw($vendor->code)" :subtitle="$isNew ? null : raw($vendor->name)" panel="top" kind="master"
        :status="$isNew ? 'new' : ($vendor->is_active ? 'active' : 'inactive')" :back="route('ac-vendors.index')" :new-url="route('ac-vendors.create')"
        :action="$isNew ? route('ac-vendors.store') : route('ac-vendors.update', $vendor)" :hidden="$isNew ? [] : ['_method' => 'PUT']"
        :created-at="$vendor->created_at?->format('Y-m-d H:i:s')" :updated-at="$vendor->updated_at?->format('Y-m-d H:i:s')">
        <x-dl::form.row :cols="3">
            <x-dl::field.text name="code" label="Kode Vendor" :value="$v('code')" code :case-toggle="false" maxlength="20" :error="$e('code')" hint="Unik." />
            <x-dl::field.text name="name" label="Nama Vendor" :value="$v('name')" required maxlength="100" :error="$e('name')" />
            <x-dl::field.text name="contact_name" label="Nama Kontak" :value="$v('contact_name')" maxlength="100" :error="$e('contact_name')" />
        </x-dl::form.row>
        <x-dl::form.row :cols="3">
            <x-dl::field.text name="phone" label="WhatsApp" :value="$v('phone')" maxlength="30" :error="$e('phone')" hint="Mis. 0812 3456 7890. Diubah otomatis ke 62... saat membuat tautan WhatsApp." />
            <x-dl::field.text name="email" label="E-mail" :value="$v('email')" maxlength="150" :error="$e('email')" hint="Tujuan e-mail pemberitahuan kunjungan." />
        </x-dl::form.row>
        <x-dl::form.row>
            <x-dl::field.text name="note" label="Catatan" :value="$v('note')" maxlength="255" :error="$e('note')" />
        </x-dl::form.row>
    </x-dl::form>
@endsection
