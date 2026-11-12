{{-- REMARK
 Tujuan       : Form Permintaan Pembelian (PP, Master-Detail): header (nomor, tanggal, pemohon, bagian peminta, dibutuhkan tgl, catatan) + baris barang (x-dl2::lines): barang master (lookup item_pp) atau ketik nama barang baru, Qty, Satuan, Estimasi harga satuan, Jumlah, Dipesan.
                Siklus tombol bawaan DevLib: Draft (Save, Confirm = ajukan, Delete) > Approval (Supervisor: Approve / Reject; pembuat: Cancel) > Approved (Cancel) | Rejected (Duplicate) | Cancelled (Duplicate).
 Sumber hulu  : PurchaseRequestController (pr, rows, log, departments), Lookup item_pp (ItemLookups).
 Input        : pr, rows, log, departments.
 Output       : POST pr.store (baru = draf) / PUT pr.update (save, confirm, approve, reject, cancel, delete, duplicate).
 Dipakai oleh : routes pr.create, pr.show.
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Permintaan Pembelian'])
@section('body')
    @php
        $isNew = ! $pr->exists;
        $me = auth()->user();
        $progress = in_array($pr->status, ['partial', 'done'], true);   // dipesan sebagian / selesai: DevLib menampilkannya sebagai Approved; Cancel disembunyikan
        $status = $isNew ? 'new' : ($progress ? 'approved' : $pr->status);
        $printable = in_array($pr->status, ['approved', 'partial', 'done'], true);
        $locked = ! $isNew && $pr->status !== 'draft';
        $mine = $isNew || $me->id === $pr->created_by;
        $v = fn (string $k) => old($k, $pr->{$k});
        $d = fn (string $k) => ($x = old($k, $pr->{$k})) ? \Illuminate\Support\Carbon::parse($x)->format('Y-m-d') : null;
        $e = fn (string $k) => $errors->first($k) ?: null;
        $columns = [
            ['key' => 'item_id', 'label' => 'Item', 'type' => 'lookup', 'lookup' => 'item_pp', 'width' => '300px'],
            ['key' => 'item_name', 'label' => 'Barang baru (ketik)', 'width' => '190px'],
            ['key' => 'qty', 'label' => 'Qty', 'type' => 'number', 'required' => true, 'width' => '100px'],
            ['key' => 'unit', 'label' => 'Unit', 'width' => '90px'],
            ['key' => 'est_price', 'label' => 'Estimasi harga satuan', 'type' => 'money', 'width' => '160px'],
            ['key' => 'subtotal', 'label' => 'Subtotal', 'type' => 'money', 'formula' => 'qty * est_price', 'sum' => true, 'width' => '150px'],
            ['key' => 'ordered_qty', 'label' => 'Dipesan', 'type' => 'display', 'width' => '90px'],
            ['key' => 'note', 'label' => 'Line Note', 'width' => '200px'],
        ];
    @endphp
    @include('partials.flash')
    @if ($progress)
        <x-dl2::alert tone="info" :message="raw($pr->statusLabel().': kolom Dipesan menunjukkan jumlah yang sudah masuk PO.')" />
    @endif
    @if (in_array($pr->status, ['approved', 'partial'], true) && $me->canAccess('PUK'))
        <p><x-dl2::button class="sstock-btn-amber" variant="secondary" size="sm" icon="fa-scale-balanced" label="Keputusan Pesanan" :href="route('po-decision.index', ['mode' => 'pp'])" /></p>
    @endif
    @if ($pr->exists && $pr->status === 'rejected')
        <x-dl2::alert tone="warn" :message="raw('Ditolak oleh '.($pr->approver?->name ?? '-').'. Alasan: '.$pr->reason.'. Pakai Duplicate untuk membuat draf baru dari PP ini.')" />
    @endif
    <x-dl2::form :title="'Permintaan Pembelian · '.($isNew ? 'Baru' : raw($pr->number()))" :subtitle="$isNew ? null : raw($pr->department?->name)" panel="top" kind="document" :status="$status"
        :back="route('pr.index')" :new-url="route('pr.create')"
        :action="$isNew ? route('pr.store') : route('pr.update', $pr)" :method="$isNew ? 'post' : 'put'"
        :pipeline="true" :approval-levels="1" :approval-at="1" :can-approve="$me->canApprove() && ! $mine"
        :hide="array_merge(['hold', 'email'], $printable ? [] : ['print'], $progress ? ['cancel'] : [])" :print-url="$printable ? route('pr.print', $pr) : null" :log="$log"
        :created-by="$pr->creator?->name" :created-at="$pr->created_at?->format('Y-m-d H:i:s')"
        :updated-by="$pr->approver?->name ?? $pr->creator?->name" :updated-at="$pr->updated_at?->format('Y-m-d H:i:s')">
        <x-dl2::form.row :cols="3">
            <x-dl2::field.display label="Document No." :value="raw($pr->doc_no ?? '(otomatis)')" />
            <x-dl2::field.date name="doc_date" label="Tanggal" :value="$d('doc_date')" required :error="$e('doc_date')" />
            <x-dl2::field.display label="Pemohon" :value="raw($pr->creator?->name ?? $me->name)" />
        </x-dl2::form.row>
        <x-dl2::form.row :cols="3">
            <x-dl2::field.select name="department_id" label="Bagian peminta" :options="$departments" :value="$v('department_id')" required :error="$e('department_id')" />
            <x-dl2::field.date name="need_date" label="Dibutuhkan tgl" :value="$d('need_date')" :error="$e('need_date')" />
            <x-dl2::field.text name="note" label="Catatan" :value="$v('note')" maxlength="255" :error="$e('note')" />
        </x-dl2::form.row>
        <x-slot:detail>
            <x-dl2::lines name="lines" title="Baris" add-label="+ Tambah Barang" :rows="$rows" :min-rows="$locked ? 0 : 1" :readonly="$locked" :row-state="true" :columns="$columns" />
            <p class="ak-note">Pilih barang dari master, atau ketik nama barang baru dengan satuannya. Satuan kosong diisi satuan barang; estimasi harga satuan kosong diisi harga beli terakhir bila barang pernah dibeli.</p>
        </x-slot:detail>
    </x-dl2::form>
@endsection
