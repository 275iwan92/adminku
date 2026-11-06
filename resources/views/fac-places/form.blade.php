{{-- REMARK
 Tujuan       : Form master Gedung / Ruang (Single Form kind=master): kode (otomatis dari nama), nama, gedung induk (kosong = gedung), penanggung jawab, catatan; daftar ruang bila ini gedung.
 Sumber hulu  : FacPlaceController (place, buildings).
 Input        : place, buildings.
 Output       : POST fac-places.store (baru) / PUT fac-places.update (ubah, Aktifkan, Nonaktifkan).
 Dipakai oleh : routes fac-places.create, fac-places.show.
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Gedung dan Ruang'])
@section('body')
    @php
        $isNew = ! $place->exists;
        $v = fn (string $k) => old($k, $place->{$k});
        $e = fn (string $k) => $errors->first($k) ?: null;
    @endphp
    @include('partials.flash')
    <x-dl::form :title="($place->parent_id ? 'Ruang' : 'Gedung').' · '.($isNew ? 'Baru' : raw($place->code))" :subtitle="$isNew ? null : raw($place->name)" panel="top" kind="master"
        :status="$isNew ? 'new' : ($place->is_active ? 'active' : 'inactive')" :back="route('fac-places.index')" :new-url="route('fac-places.create')"
        :action="$isNew ? route('fac-places.store') : route('fac-places.update', $place)" :hidden="$isNew ? [] : ['_method' => 'PUT']"
        :created-at="$place->created_at?->format('Y-m-d H:i:s')" :updated-at="$place->updated_at?->format('Y-m-d H:i:s')">
        <x-dl::form.row :cols="3">
            <x-dl::field.text name="code" label="Kode" :value="$v('code')" code :case-toggle="false" maxlength="20" :error="$e('code')" hint="Kosong = otomatis dari nama." />
            <x-dl::field.text name="name" label="Nama" :value="$v('name')" required maxlength="100" :error="$e('name')" />
            <x-dl::field.select name="parent_id" label="Gedung induk" :options="['' => '(ini sebuah gedung)'] + $buildings" :value="$v('parent_id')" :error="$e('parent_id')" hint="Pilih gedung bila ini sebuah ruang / area." />
        </x-dl::form.row>
        <x-dl::form.row :cols="2">
            <x-dl::field.text name="pic_name" label="Penanggung jawab" :value="$v('pic_name')" maxlength="100" :error="$e('pic_name')" />
            <x-dl::field.text name="note" label="Catatan" :value="$v('note')" maxlength="255" :error="$e('note')" />
        </x-dl::form.row>
        @if ($place->exists && $place->children->isNotEmpty())
            <p class="dl-note">Ruang di gedung ini: {{ $place->children->pluck('name')->implode(', ') }}.</p>
        @endif
    </x-dl::form>
@endsection
