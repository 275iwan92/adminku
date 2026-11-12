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
    <x-dl2::form :title="$isNew ? 'Sumber Dana · Baru' : raw($source->name)" panel="top" kind="master" :deletable="! $isNew"
        :status="$isNew ? 'new' : ($source->is_active ? 'active' : 'inactive')" :back="route('cash-sources.index')" :new-url="route('cash-sources.create')"
        :action="$isNew ? route('cash-sources.store') : route('cash-sources.update', $source)" :method="$isNew ? 'post' : 'put'"
        :created-at="$source->created_at?->format('Y-m-d H:i:s')" :updated-at="$source->updated_at?->format('Y-m-d H:i:s')">
        <x-dl2::form.row>
            <x-dl2::field.text name="name" label="Sumber Dana" :value="old('name', $source->name)" required maxlength="150" :error="$e('name')" hint="Mis. 'Donatur Bapak Hadi' atau 'Pendapatan operasional'." />
        </x-dl2::form.row>
    </x-dl2::form>
@endsection
