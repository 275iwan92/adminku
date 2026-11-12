{{-- REMARK
 Tujuan       : Form grup pengguna (Single Form kind=master): kode (tetap setelah disimpan), nama, dan tabel hak akses per modul (Tidak ada / RO / RW).
 Sumber hulu  : GroupController (group, rows = satu baris per modul dengan access none|ro|rw).
 Input        : group, rows.
 Output       : POST groups.store (baru) / PUT groups.update (ubah nama + modul).
 Dipakai oleh : routes groups.create, groups.show.
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Group'])
@section('body')
    @php
        $isNew = ! $group->exists;
        $v = fn (string $k) => old($k, $group->{$k});
        $e = fn (string $k) => $errors->first($k) ?: null;
    @endphp
    @include('partials.flash')
    <x-dl2::form :title="$isNew ? 'Grup · Baru' : 'Grup · '.raw($group->code)" :subtitle="$isNew ? null : raw($group->name)" panel="top" kind="master"
        :status="$isNew ? 'new' : ($group->is_active ? 'active' : 'inactive')" :back="route('groups.index')" :new-url="route('groups.create')" :hide="['duplicate', 'print', 'email']"
        :action="$isNew ? route('groups.store') : route('groups.update', $group)" :method="$isNew ? 'post' : 'put'"
        :created-at="$group->created_at?->format('Y-m-d H:i:s')" :updated-at="$group->updated_at?->format('Y-m-d H:i:s')">
        <x-dl2::form.row :cols="3">
            <x-dl2::field.text name="code" label="Group Code" :value="$v('code')" code :case-toggle="false" required :readonly="! $isNew" maxlength="20" :error="$e('code')" hint="Unique. Cannot be changed after saving." />
            <x-dl2::field.text name="name" label="Group Name" :value="$v('name')" required maxlength="100" :error="$e('name')" />
            @unless ($isNew)
                <x-dl2::field.display label="Members" :value="(string) $group->users_count" />
            @endunless
        </x-dl2::form.row>
        
            <x-dl2::lines name="modules" key-field="code" title="Access per module" :rows="$rows" :addable="false" :duplicable="false" :min-rows="count($rows)"
                :columns="[
                    ['key' => 'label', 'label' => 'Module', 'type' => 'display', 'width' => '260px'],
                    ['key' => 'menu', 'label' => 'Menu', 'type' => 'display', 'width' => '160px'],
                    ['key' => 'level', 'label' => 'Minimum Role', 'type' => 'display', 'width' => '160px'],
                    ['key' => 'access', 'label' => 'Access', 'type' => 'select', 'options' => ['none' => 'Tidak ada', 'ro' => 'Hanya baca (RO)', 'rw' => 'Baca & tulis (RW)'], 'width' => '220px'],
                ]" />
            <p class="ak-note">{{ \Devlib2\Lang::text('RO opens the screen but blocks saving or changing anything. A user also needs a high enough role: Supervisor-only and Administrator-only modules stay closed to lower roles even when granted. Administrators always have full access.') }}</p>
        
    </x-dl2::form>
@endsection
