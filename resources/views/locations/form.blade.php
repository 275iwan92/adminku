{{-- REMARK
 Tujuan       : Form master Lokasi Penyimpanan (Single Form kind=master): kode (tetap setelah disimpan) dan nama.
 Sumber hulu  : LocationController (location).
 Input        : location.
 Output       : POST locations.store (baru) / PUT locations.update (ubah nama, Activate, Deactivate).
 Dipakai oleh : routes locations.create, locations.show.
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Storage Location'])
@section('body')
    @php
        $isNew = ! $location->exists;
        $v = fn (string $k) => old($k, $location->{$k});
        $e = fn (string $k) => $errors->first($k) ?: null;
    @endphp
    @include('partials.flash')
    <x-dl::form :title="$isNew ? 'Lokasi · Baru' : 'Lokasi · '.raw($location->code)" :subtitle="$isNew ? null : raw($location->name)" panel="top" kind="master"
        :status="$isNew ? 'new' : ($location->is_active ? 'active' : 'inactive')" :back="route('locations.index')" :new-url="route('locations.create')"
        :action="$isNew ? route('locations.store') : route('locations.update', $location)" :hidden="$isNew ? [] : ['_method' => 'PUT']"
        :created-at="$location->created_at?->format('Y-m-d H:i:s')" :updated-at="$location->updated_at?->format('Y-m-d H:i:s')">
        <x-dl::form.row :cols="3">
            <x-dl::field.text name="code" label="Location Code" :value="$v('code')" code :case-toggle="false" required :readonly="! $isNew" maxlength="50" :error="$e('code')" hint="Unique, for example A1-01. Cannot be changed after saving." />
            <x-dl::field.text name="name" label="Location Name" :value="$v('name')" required maxlength="100" :error="$e('name')" />
        </x-dl::form.row>
        @unless ($isNew)
            <x-dl::form.row :cols="3">
                <x-dl::field.display label="Items using it" :value="(string) $location->items_count" />
            </x-dl::form.row>
        @endunless
    </x-dl::form>
@endsection
