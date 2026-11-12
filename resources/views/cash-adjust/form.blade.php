{{-- REMARK
 Tujuan       : Form Penyesuaian Kas (Single Form): kas, arah (tambah / kurangi), jumlah, alasan wajib. Siklus sama Uang Keluar; Approve oleh Supervisor menulis baris buku besar kas.
 Sumber hulu  : CashAdjustmentController (doc, log, accounts).
 Input        : doc, log, accounts.
 Output       : POST cash-adjust.store (baru = draf) / PUT cash-adjust.update (save, confirm, approve, reject, cancel, delete, duplicate).
 Dipakai oleh : routes cash-adjust.create, cash-adjust.show.
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Penyesuaian Kas'])
@section('body')
    @php
        $isNew = ! $doc->exists;
        $me = auth()->user();
        $mine = $isNew || $me->id === $doc->created_by;
        $v = fn (string $k) => old($k, $doc->{$k});
        $d = fn (string $k) => ($x = old($k, $doc->{$k})) ? \Illuminate\Support\Carbon::parse($x)->format('Y-m-d') : null;
        $e = fn (string $k) => $errors->first($k) ?: null;
    @endphp
    @include('partials.flash')
    @if ($doc->exists && $doc->status === 'rejected')
        <x-dl2::alert tone="warn" :message="raw('Ditolak oleh '.($doc->approver?->name ?? '-').'. Alasan: '.$doc->reason.'. Pakai Duplicate untuk membuat draf baru.')" />
    @endif
    <x-dl2::form :title="'Penyesuaian Kas · '.($isNew ? 'Baru' : raw($doc->number()))" :subtitle="$isNew ? null : raw($doc->cause)" panel="top" kind="document" :status="$isNew ? 'new' : $doc->status"
        :back="route('cash-adjust.index')" :new-url="route('cash-adjust.create')"
        :action="$isNew ? route('cash-adjust.store') : route('cash-adjust.update', $doc)" :method="$isNew ? 'post' : 'put'"
        :pipeline="true" :approval-levels="1" :approval-at="1" :can-approve="$me->canApprove() && ! $mine"
        :hide="['hold', 'email', 'print']" :log="$log"
        :created-by="$doc->creator?->name" :created-at="$doc->created_at?->format('Y-m-d H:i:s')"
        :updated-by="$doc->approver?->name ?? $doc->creator?->name" :updated-at="$doc->updated_at?->format('Y-m-d H:i:s')">
        <x-dl2::form.row class="sstock-split">
            <x-dl2::form.col>
                <x-dl2::field.display label="No. Penyesuaian" :value="raw($doc->doc_no ?? '(otomatis)')" />
                <x-dl2::field.date name="doc_date" label="Tanggal" :value="$d('doc_date')" required :error="$e('doc_date')" />
                <x-dl2::field.text name="cause" label="Alasan Penyesuaian" :value="$v('cause')" maxlength="255" required :error="$e('cause')" hint="Wajib. Jelaskan mengapa saldo kas dikoreksi (mis. selisih hitung fisik, salah catat)." />
            </x-dl2::form.col>
            <x-dl2::form.col class="sstock-panel">
                <x-dl2::field.select name="cash_account_id" label="Kas / Rekening" :options="['' => 'Pilih kas...'] + $accounts" :value="$v('cash_account_id')" required :error="$e('cash_account_id')" />
                <x-dl2::field.select name="direction" label="Arah" :options="\App\Models\CashAdjustment::DIRECTIONS" :value="$v('direction')" required :error="$e('direction')" />
                <x-dl2::field.decimal name="amount" label="Jumlah (IDR)" :decimals="2" :value="$v('amount')" required :error="$e('amount')" />
            </x-dl2::form.col>
        </x-dl2::form.row>
    </x-dl2::form>
@endsection
