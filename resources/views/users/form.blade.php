{{-- REMARK
 Tujuan       : Form pengguna (Single Form kind=master): nama, username (tetap setelah disimpan), e-mail, peran, password (kosong saat ubah = tidak diganti).
 Sumber hulu  : UserController (user).
 Input        : user.
 Output       : POST users.store (baru) / PUT users.update (ubah, Activate, Deactivate).
 Dipakai oleh : routes users.create, users.show.
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'User'])
@section('body')
    @php
        $isNew = ! $user->exists;
        $self = $user->is(auth()->user());
        $v = fn (string $k) => old($k, $user->{$k});
        $e = fn (string $k) => $errors->first($k) ?: null;
    @endphp
    @include('partials.flash')
    <x-dl::form :title="$isNew ? 'Pengguna · Baru' : 'Pengguna · '.raw($user->username)" :subtitle="$isNew ? null : raw($user->name)" panel="top" kind="master"
        :status="$isNew ? 'new' : ($user->is_active ? 'active' : 'inactive')" :back="route('users.index')" :new-url="route('users.create')"
        :action="$isNew ? route('users.store') : route('users.update', $user)" :hidden="$isNew ? [] : ['_method' => 'PUT']"
        :created-at="$user->created_at?->format('Y-m-d H:i:s')" :updated-at="$user->updated_at?->format('Y-m-d H:i:s')">
        <x-dl::form.row :cols="3">
            <x-dl::field.text name="name" label="Full Name" :value="$v('name')" required maxlength="100" :error="$e('name')" />
            <x-dl::field.text name="username" label="Username" :value="$v('username')" code :case-toggle="false" required :readonly="! $isNew" maxlength="50" :error="$e('username')" hint="Lowercase. Cannot be changed after saving." />
            <x-dl::field.text name="email" label="Email" :value="$v('email')" required maxlength="150" :error="$e('email')" />
        </x-dl::form.row>
        <x-dl::form.row :cols="3">
            <x-dl::field.select name="role" label="Role" :options="\App\Models\User::ROLES" :value="$v('role')" required :readonly="$self" :error="$e('role')" tooltip="Data Entry: receiving and issuance. Admin Data: opens every screen but cannot approve. Supervisor: all stock functions and approval. Administrator: Supervisor + Security menu." />
            <x-dl::field.select name="group_id" label="Group" :options="$groups" :value="$v('group_id')" :error="$e('group_id')" hint="Decides which modules this user can open. Empty = the default modules of the role." />
            <x-dl::field.password name="password" label="Password" autocomplete="new-password" :required="$isNew" :error="$e('password')" :hint="$isNew ? 'At least 8 characters.' : 'Leave empty to keep the current password.'" />
        </x-dl::form.row>
    </x-dl::form>
@endsection
