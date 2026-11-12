{{-- REMARK
 Tujuan       : Form master Kas / Rekening (Single Form kind=master): nama, jenis (Tunai/Bank), bank, no. rekening, atas nama, saldo awal, saldo berjalan (hanya tampil).
 Sumber hulu  : CashAccountController (account).
 Input        : account.
 Output       : POST cash-accounts.store (baru) / PUT cash-accounts.update (ubah, Activate, Deactivate).
 Dipakai oleh : routes cash-accounts.create, cash-accounts.show.
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Kas / Rekening'])
@section('body')
    @php
        $isNew = ! $account->exists;
        $v = fn (string $k) => old($k, $account->{$k});
        $e = fn (string $k) => $errors->first($k) ?: null;
    @endphp
    @include('partials.flash')
    <x-dl2::form :title="$isNew ? 'Kas / Rekening · Baru' : raw($account->name)" :subtitle="$isNew ? null : $account->typeLabel()" panel="top" kind="master"
        :status="$isNew ? 'new' : ($account->is_active ? 'active' : 'inactive')" :back="route('cash-accounts.index')" :new-url="route('cash-accounts.create')"
        :action="$isNew ? route('cash-accounts.store') : route('cash-accounts.update', $account)" :method="$isNew ? 'post' : 'put'"
        :created-at="$account->created_at?->format('Y-m-d H:i:s')" :updated-at="$account->updated_at?->format('Y-m-d H:i:s')">
        <x-dl2::form.row :cols="3">
            <x-dl2::field.text name="name" label="Nama" :value="$v('name')" required maxlength="100" :error="$e('name')" />
            <x-dl2::field.select name="type" label="Jenis" :options="\App\Models\CashAccount::TYPES" :value="$v('type')" required :error="$e('type')" />
            <x-dl2::field.decimal name="opening_balance" label="Saldo Awal (IDR)" :decimals="2" :value="$v('opening_balance')" required :error="$e('opening_balance')" />
        </x-dl2::form.row>
        <x-dl2::form.row :cols="3">
            <x-dl2::field.text name="bank_name" label="Nama Bank" :value="$v('bank_name')" maxlength="100" :error="$e('bank_name')" />
            <x-dl2::field.text name="account_no" label="No. Rekening" :value="$v('account_no')" maxlength="30" :error="$e('account_no')" />
            <x-dl2::field.text name="account_holder" label="Atas Nama" :value="$v('account_holder')" maxlength="100" :error="$e('account_holder')" />
        </x-dl2::form.row>
        @unless ($isNew)
            <x-dl2::form.row :cols="3">
                <x-dl2::field.display label="Saldo Berjalan" :value="'Rp '.number_format($account->currentBalance(), 0, ',', '.')" />
            </x-dl2::form.row>
        @endunless
    </x-dl2::form>
@endsection
