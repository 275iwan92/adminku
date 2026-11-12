{{-- REMARK
 Tujuan       : Form Uang Masuk (Single Form): kas tujuan, sumber dana, jumlah, lampiran bukti. Siklus tombol DevLib: Draft (Save, Confirm, Delete) > Approval (Supervisor: Approve/Reject; pembuat: Cancel) > Approved (Cancel) | Rejected (Duplicate) | Cancelled (Duplicate).
                Setelah Disetujui, panel detail menampilkan semua Uang Keluar yang ditautkan ke sini beserta total terpakai dan sisa.
 Sumber hulu  : CashInController (cashIn, log, accounts, cashOuts).
 Input        : cashIn, log, accounts, cashOuts.
 Output       : POST cash-in.store (baru = draf) / PUT cash-in.update (save, confirm, approve, reject, cancel, delete, duplicate).
 Dipakai oleh : routes cash-in.create, cash-in.show.
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Uang Masuk'])
@section('body')
    @php
        $isNew = ! $cashIn->exists;
        $me = auth()->user();
        $mine = $isNew || $me->id === $cashIn->created_by;
        $v = fn (string $k) => old($k, $cashIn->{$k});
        $d = fn (string $k) => ($x = old($k, $cashIn->{$k})) ? \Illuminate\Support\Carbon::parse($x)->format('Y-m-d') : null;
        $e = fn (string $k) => $errors->first($k) ?: null;
        $tones = ['Draf' => 'neutral', 'Diajukan' => 'warn', 'Disetujui' => 'success', 'Ditolak' => 'danger', 'Dibatalkan' => 'danger'];
    @endphp
    @include('partials.flash')
    @if ($cashIn->exists && $cashIn->status === 'rejected')
        <x-dl2::alert tone="warn" :message="raw('Ditolak oleh '.($cashIn->approver?->name ?? '-').'. Alasan: '.$cashIn->reason.'. Pakai Duplicate untuk membuat draf baru.')" />
    @endif
    <x-dl2::form :title="'Uang Masuk · '.($isNew ? 'Baru' : raw($cashIn->number()))" :subtitle="$isNew ? null : raw($cashIn->source_name)" panel="top" kind="document" :status="$isNew ? 'new' : $cashIn->status"
        :back="route('cash-in.index')" :new-url="route('cash-in.create')"
        :action="$isNew ? route('cash-in.store') : route('cash-in.update', $cashIn)" :method="$isNew ? 'post' : 'put'" enctype="multipart/form-data"
        :pipeline="true" class="sstock-cash sstock-cash-in" :approval-levels="1" :approval-at="1" :can-approve="$me->canApprove() && ! $mine"
        :hide="['hold', 'email', 'print']" :log="$log"
        :created-by="$cashIn->creator?->name" :created-at="$cashIn->created_at?->format('Y-m-d H:i:s')"
        :updated-by="$cashIn->approver?->name ?? $cashIn->creator?->name" :updated-at="$cashIn->updated_at?->format('Y-m-d H:i:s')">
        <x-dl2::form.row class="sstock-split">
            <x-dl2::form.col>
                <x-dl2::field.display label="No. Uang Masuk" :value="raw($cashIn->doc_no ?? '(otomatis)')" />
                <x-dl2::field.date name="doc_date" label="Tanggal" :value="$d('doc_date')" required :error="$e('doc_date')" />
                <x-dl2::field.select name="source_name" label="Sumber Dana" :options="['' => 'Pilih sumber dana...'] + $sources + ($v('source_name') && ! isset($sources[$v('source_name')]) ? [$v('source_name') => $v('source_name')] : [])" :value="$v('source_name')" required :add="\App\Support\QuickAdd::for('cash_source')" :error="$e('source_name')" />
                <x-dl2::field.text name="description" label="Keterangan" :value="$v('description')" maxlength="255" :error="$e('description')" />
                <x-dl2::field.upload name="attachment" label="Lampiran Bukti" :value="$cashIn->attachment ? ['url' => route('cash-in.file', $cashIn), 'name' => 'Lampiran bukti ('.strtoupper(pathinfo($cashIn->attachment, PATHINFO_EXTENSION)).')'] : null" :preview="! str_ends_with(strtolower($cashIn->attachment ?? ''), '.pdf')" accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf" :error="$e('attachment')" hint="Foto atau PDF bukti penerimaan dana (tidak wajib). JPG, PNG, atau PDF, paling besar 5 MB." />
            </x-dl2::form.col>
            <x-dl2::form.col class="sstock-panel">
                <p class="sstock-cashhelp">Pilih salah satu: Tunai atau Rekening bank.</p>
                <x-dl2::field.toggle-group name="cash_kind" label="Kas Tujuan" :options="['cash' => 'Tunai', 'bank' => 'Rekening bank']" :value="old('cash_kind', $kind)" />
                <x-dl2::field.select class="sstock-cashbank" name="cash_account_id" label="Rekening Bank" :options="['' => 'Pilih rekening...'] + $banks" :value="$v('cash_account_id')" :add="\App\Support\QuickAdd::for('cash_bank')" :error="$e('cash_account_id')" />
                <x-dl2::field.decimal class="sstock-amount" name="amount" label="Jumlah (IDR)" :decimals="2" :value="$v('amount')" required :error="$e('amount')" />
            </x-dl2::form.col>
        </x-dl2::form.row>
        @if ($cashIn->exists && $cashIn->status === 'approved')
            
                <x-dl2::grid id="cash-outs" :rows="$cashOuts" actions="worklist" :open-url="url('cash-out/{id}')"
                    search="Cari untuk apa..."
                    :columns="[
                        ['key' => 'doc_no', 'label' => 'No. Uang Keluar'],
                        ['key' => 'doc_date', 'label' => 'Tanggal', 'type' => 'date'],
                        ['key' => 'purpose', 'label' => 'Untuk Apa'],
                        ['key' => 'amount', 'label' => 'Jumlah', 'type' => 'money', 'sum' => true],
                        ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => $tones],
                    ]"
                    :stats="[['label' => 'Semua Uang Keluar']]" />
                <p class="ak-note sstock-cashnote">Terpakai Rp {{ number_format($cashIn->usedAmount(), 0, ',', '.') }} dari Rp {{ number_format((float) $cashIn->amount, 0, ',', '.') }} — sisa Rp {{ number_format($cashIn->remainingAmount(), 0, ',', '.') }}.</p>
            
        @endif
    </x-dl2::form>
@endsection
