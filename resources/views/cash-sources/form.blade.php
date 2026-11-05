{{-- REMARK
 Tujuan       : Form Sumber Dana: nama; Delete tersedia (nama sudah disalin ke Uang Masuk lama, jadi dokumen lama tidak berubah).
 Sumber hulu  : CashSourceController (source).
 Output       : POST cash-sources.store / PUT cash-sources.update (save, delete).
 Dipakai oleh : routes cash-sources.create, cash-sources.show.
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Sumber Dana'])
@section('body')
    @php
        $isNew = ! $source->exists;
        $e = fn (string $k) => $errors->first($k) ?: null;
    @endphp
    @include('partials.flash')
    <x-dl::form :title="$isNew ? 'Sumber Dana · Baru' : raw($source->name)" panel="top" kind="master" :deletable="! $isNew"
        :status="$isNew ? 'new' : 'active'" :back="route('cash-sources.index')" :new-url="route('cash-sources.create')"
        :action="$isNew ? route('cash-sources.store') : route('cash-sources.update', $source)" :hidden="$isNew ? [] : ['_method' => 'PUT']"
        :created-at="$source->created_at?->format('Y-m-d H:i:s')" :updated-at="$source->updated_at?->format('Y-m-d H:i:s')">
        <x-dl::form.row>
            <x-dl::field.text name="name" label="Sumber Dana" :value="old('name', $source->name)" required maxlength="150" :error="$e('name')" hint="Mis. 'Donatur Bapak Hadi' atau 'Pendapatan operasional'." />
        </x-dl::form.row>
    </x-dl::form>
@endsection
