{{-- REMARK
 Tujuan       : Form Uang Keluar (Single Form): Uang Masuk tertaut (dropdown menampilkan sisa saldo), jumlah (tidak boleh melebihi sisa), untuk apa, lampiran bukti. Kas asal ikut otomatis kas tujuan Uang Masuk yang ditaut. Siklus sama Uang Masuk / Purchase.
 Sumber hulu  : CashOutController (cashOut, log, cashIns).
 Input        : cashOut, log, cashIns.
 Output       : POST cash-out.store (baru = draf) / PUT cash-out.update (save, confirm, approve, reject, cancel, delete, duplicate).
 Dipakai oleh : routes cash-out.create, cash-out.show.
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Uang Keluar'])
@section('body')
    @php
        $isNew = ! $cashOut->exists;
        $me = auth()->user();
        $mine = $isNew || $me->id === $cashOut->created_by;
        $v = fn (string $k) => old($k, $cashOut->{$k});
        $d = fn (string $k) => ($x = old($k, $cashOut->{$k})) ? \Illuminate\Support\Carbon::parse($x)->format('Y-m-d') : null;
        $e = fn (string $k) => $errors->first($k) ?: null;
        $account = $cashOut->exists ? $cashOut->cashAccount() : null;
    @endphp
    @include('partials.flash')
    @if ($cashOut->exists && $cashOut->status === 'rejected')
        <x-dl::alert tone="warn" :message="raw('Ditolak oleh '.($cashOut->approver?->name ?? '-').'. Alasan: '.$cashOut->reason.'. Pakai Duplicate untuk membuat draf baru.')" />
    @endif
    <x-dl::form :title="'Uang Keluar · '.($isNew ? 'Baru' : raw($cashOut->number()))" :subtitle="$isNew ? null : raw($cashOut->purpose)" panel="top" kind="document" :status="$isNew ? 'new' : $cashOut->status"
        :back="route('cash-out.index')" :new-url="route('cash-out.create')"
        :action="$isNew ? route('cash-out.store') : route('cash-out.update', $cashOut)" :hidden="$isNew ? [] : ['_method' => 'PUT']" enctype="multipart/form-data"
        :pipeline="true" class="sstock-cash" :approval-levels="1" :approval-at="1" :can-approve="$me->canApprove() && ! $mine"
        :hide="['hold', 'email', 'print']" :log="$log"
        :created-by="$cashOut->creator?->name" :created-at="$cashOut->created_at?->format('Y-m-d H:i:s')"
        :updated-by="$cashOut->approver?->name ?? $cashOut->creator?->name" :updated-at="$cashOut->updated_at?->format('Y-m-d H:i:s')">
        <x-dl::form.row :cols="3">
            <x-dl::field.display label="No. Uang Keluar" :value="raw($cashOut->doc_no ?? '(otomatis)')" />
            <x-dl::field.date name="doc_date" label="Tanggal" :value="$d('doc_date')" required :error="$e('doc_date')" />
            <x-dl::field.select name="cash_in_id" label="Ditaut Ke Uang Masuk" :options="$cashIns" :value="$v('cash_in_id')" required :error="$e('cash_in_id')" hint="Hanya Uang Masuk yang sudah Disetujui. Sisa saldo tampil di tiap pilihan." />
        </x-dl::form.row>
        <x-dl::form.row :cols="2">
            <x-dl::field.decimal class="sstock-amount" name="amount" label="Jumlah (IDR)" :decimals="2" :value="$v('amount')" required :error="$e('amount')" />
            <x-dl::field.text name="purpose" label="Untuk Apa" :value="$v('purpose')" required maxlength="150" :error="$e('purpose')" />
        </x-dl::form.row>
        <x-dl::form.row :cols="2">
            <x-dl::field.display label="Kas Asal" :value="raw($account?->name ?? '(ikut Uang Masuk yang dipilih)')" />
            <x-dl::field.text name="description" label="Keterangan" :value="$v('description')" maxlength="255" :error="$e('description')" />
        </x-dl::form.row>
        <x-dl::form.row>
            <x-dl::field.upload name="attachment" label="Lampiran Bukti" accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf" :error="$e('attachment')" hint="Foto atau PDF bukti pengeluaran (tidak wajib). JPG, PNG, atau PDF, paling besar 5 MB." />
        </x-dl::form.row>
    </x-dl::form>
@endsection
