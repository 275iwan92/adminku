{{-- REMARK
 Tujuan       : Form Kebutuhan: nama; Delete tersedia (nama sudah disalin ke Uang Keluar lama, jadi dokumen lama tidak berubah).
 Sumber hulu  : CashPurposeController (source).
 Output       : POST cash-purposes.store / PUT cash-purposes.update (save, delete).
 Dipakai oleh : routes cash-purposes.create, cash-purposes.show.
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Kebutuhan'])
@section('body')
    @php
        $isNew = ! $purpose->exists;
        $e = fn (string $k) => $errors->first($k) ?: null;
    @endphp
    @include('partials.flash')
    <x-dl::form :title="$isNew ? 'Kebutuhan · Baru' : raw($purpose->name)" panel="top" kind="master" :deletable="! $isNew"
        :status="$isNew ? 'new' : 'active'" :back="route('cash-purposes.index')" :new-url="route('cash-purposes.create')"
        :action="$isNew ? route('cash-purposes.store') : route('cash-purposes.update', $purpose)" :hidden="$isNew ? [] : ['_method' => 'PUT']"
        :created-at="$purpose->created_at?->format('Y-m-d H:i:s')" :updated-at="$purpose->updated_at?->format('Y-m-d H:i:s')">
        <x-dl::form.row>
            <x-dl::field.text name="name" label="Kebutuhan" :value="old('name', $purpose->name)" required maxlength="150" :error="$e('name')" hint="Mis. 'Operasional kantor' atau 'Konsumsi acara'." />
        </x-dl::form.row>
    </x-dl::form>
@endsection
