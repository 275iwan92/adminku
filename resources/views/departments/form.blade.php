{{-- REMARK
 Tujuan       : Form master Departemen (Single Form kind=master): kode (tetap setelah disimpan) dan nama.
 Sumber hulu  : DepartmentController (department).
 Input        : department.
 Output       : POST departments.store (baru) / PUT departments.update (ubah, Activate, Deactivate).
 Dipakai oleh : routes departments.create, departments.show.
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Department'])
@section('body')
    @php
        $isNew = ! $department->exists;
        $v = fn (string $k) => old($k, $department->{$k});
        $e = fn (string $k) => $errors->first($k) ?: null;
    @endphp
    @include('partials.flash')
    <x-dl2::form :title="$isNew ? 'Departemen · Baru' : 'Departemen · '.raw($department->code)" :subtitle="$isNew ? null : raw($department->name)" panel="top" kind="master"
        :status="$isNew ? 'new' : ($department->is_active ? 'active' : 'inactive')" :back="route('departments.index')" :new-url="route('departments.create')"
        :action="$isNew ? route('departments.store') : route('departments.update', $department)" :method="$isNew ? 'post' : 'put'"
        :created-at="$department->created_at?->format('Y-m-d H:i:s')" :updated-at="$department->updated_at?->format('Y-m-d H:i:s')">
        <x-dl2::form.row :cols="3">
            <x-dl2::field.text name="code" label="Department Code" :value="$v('code')" code :case-toggle="false" required :readonly="! $isNew" maxlength="20" :error="$e('code')" hint="Unique. Cannot be changed after saving." />
            <x-dl2::field.text name="name" label="Department Name" :value="$v('name')" required maxlength="100" :error="$e('name')" />
        </x-dl2::form.row>
        @unless ($isNew)
            <x-dl2::form.row :cols="3">
                <x-dl2::field.display label="Recipients" :value="(string) $department->recipients_count" />
                <x-dl2::field.display label="Issuance Documents" :value="(string) $department->documents_count" />
                <x-dl2::field.display label="Employees" :value="(string) $department->employees_count" />
            </x-dl2::form.row>
        @endunless
    </x-dl2::form>
@endsection
