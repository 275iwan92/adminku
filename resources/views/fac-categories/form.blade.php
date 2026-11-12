{{-- REMARK
 Tujuan       : Form master Kategori Aset (Single Form kind=master): kode (otomatis dari nama), nama, ikon.
 Sumber hulu  : FacCategoryController (category).
 Input        : category.
 Output       : POST fac-categories.store (baru) / PUT fac-categories.update (ubah, Aktifkan, Nonaktifkan).
 Dipakai oleh : routes fac-categories.create, fac-categories.show.
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Kategori Aset'])
@section('body')
    @php
        $isNew = ! $category->exists;
        $v = fn (string $k) => old($k, $category->{$k});
        $e = fn (string $k) => $errors->first($k) ?: null;
    @endphp
    @include('partials.flash')
    <x-dl2::form :title="$isNew ? 'Kategori Aset · Baru' : 'Kategori Aset · '.raw($category->code)" :subtitle="$isNew ? null : raw($category->name)" panel="top" kind="master"
        :status="$isNew ? 'new' : ($category->is_active ? 'active' : 'inactive')" :back="route('fac-categories.index')" :new-url="route('fac-categories.create')"
        :action="$isNew ? route('fac-categories.store') : route('fac-categories.update', $category)" :method="$isNew ? 'post' : 'put'"
        :created-at="$category->created_at?->format('Y-m-d H:i:s')" :updated-at="$category->updated_at?->format('Y-m-d H:i:s')">
        <x-dl2::form.row :cols="3">
            <x-dl2::field.text name="code" label="Kode" :value="$v('code')" code :case-toggle="false" maxlength="20" :error="$e('code')" hint="Kosong = otomatis dari nama." />
            <x-dl2::field.text name="name" label="Nama Kategori" :value="$v('name')" required maxlength="100" :error="$e('name')" />
            <x-dl2::field.select name="icon" label="Ikon" :options="\App\Http\Controllers\FacCategoryController::ICONS" :value="$v('icon')" required :error="$e('icon')" />
        </x-dl2::form.row>
    </x-dl2::form>
@endsection
