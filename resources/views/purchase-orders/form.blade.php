{{-- REMARK
 Tujuan       : Form Pesanan Pembelian (PO, Master-Detail): tab Data (kepala: nomor, tanggal, pemasok, syarat bayar, tanggal kirim, kirim ke, PPN opsional, catatan + baris barang x-dl::lines dengan kolom Dari PP, Subtotal, Diterima) dan tab Riwayat.
                Siklus tombol DevLib: Draft (Save, Confirm = Ajukan) > Approval (Supervisor: Approve / Reject; pembuat: Cancel) > Approved (Cancel, Print) | Rejected (Duplicate) | Cancelled (Duplicate). Syarat bayar terisi dari Pemasok yang dipilih.
 Sumber hulu  : PurchaseOrderController (po, rows, log, suppliers, terms), Lookup item_pp (ItemLookups).
 Input        : po, rows, log, suppliers, terms (id pemasok => syarat bayar).
 Output       : POST po.store (baru = draf) / PUT po.update (save, confirm, approve, reject, cancel, duplicate).
 Dipakai oleh : routes po.create, po.show.
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Pesanan Pembelian'])
@section('body')
    @php
        $isNew = ! $po->exists;
        $me = auth()->user();
        $progress = in_array($po->status, ['partial', 'done'], true);   // diterima sebagian / selesai: DevLib menampilkannya sebagai Approved; Cancel disembunyikan
        $status = $isNew ? 'new' : ($progress ? 'approved' : $po->status);
        $locked = ! $isNew && $po->status !== 'draft';
        $mine = $isNew || $me->id === $po->created_by;
        $printable = in_array($po->status, ['approved', 'partial', 'done'], true);
        $v = fn (string $k) => old($k, $po->{$k});
        $d = fn (string $k) => ($x = old($k, $po->{$k})) ? \Illuminate\Support\Carbon::parse($x)->format('Y-m-d') : null;
        $e = fn (string $k) => $errors->first($k) ?: null;
        $columns = [
            ['key' => 'item_id', 'label' => 'Item', 'type' => 'lookup', 'lookup' => 'item_po', 'width' => '280px'],
            ['key' => 'item_name', 'label' => 'Barang baru (ketik)', 'width' => '170px'],
            ['key' => 'qty', 'label' => 'Qty', 'type' => 'number', 'required' => true, 'width' => '90px'],
            ['key' => 'unit', 'label' => 'Unit', 'width' => '80px'],
            ['key' => 'price', 'label' => 'Harga satuan', 'type' => 'money', 'required' => true, 'width' => '140px'],
            ['key' => 'subtotal', 'label' => 'Subtotal', 'type' => 'money', 'formula' => 'qty * price', 'sum' => true, 'width' => '140px'],
            ['key' => 'received_qty', 'label' => 'Diterima', 'type' => 'display', 'width' => '90px'],
            ['key' => 'pp', 'label' => 'Dari PP', 'type' => 'display', 'width' => '120px'],
            ['key' => 'note', 'label' => 'Line Note', 'width' => '180px'],
        ];
        $hide = array_merge(['hold', 'email', 'new', 'undo'], $printable ? [] : ['print'], $progress ? ['cancel'] : []);
    @endphp
    @include('partials.flash')
    @if ($progress)
        <x-dl::alert tone="info" :message="raw($po->statusLabel().': '.$po->receivedPct().'% diterima.')" />
    @endif
    @if (in_array($po->status, ['approved', 'partial'], true) && $me->canWrite('PUV'))
        <p><x-dl::button class="sstock-btn-teal" variant="secondary" size="sm" icon="fa-truck-ramp-box" label="Terima barang" :href="route('pv.create', ['po' => $po->id])" /></p>
    @endif
    @if ($po->exists && $po->status === 'rejected')
        <x-dl::alert tone="warn" :message="raw('Ditolak oleh '.($po->approver?->name ?? '-').'. Alasan: '.$po->reason.'. Pakai Duplicate untuk membuat draf baru dari PO ini.')" />
    @endif
    <x-dl::form :title="'Pesanan Pembelian · '.($isNew ? 'Baru' : raw($po->number()))" :subtitle="$isNew ? null : raw($po->supplier?->name)" panel="top" kind="document" :status="$status"
        :back="route('po.index')" :new-url="route('po.create')" :log="$log"
        :action="$isNew ? route('po.store') : route('po.update', $po)" :hidden="$isNew ? [] : ['_method' => 'PUT']"
        :pipeline="true" :approval-levels="1" :approval-at="1" :can-approve="$me->canApprove() && ! $mine"
        :hide="$hide" :print-url="$printable ? route('po.print', $po) : null"
        :created-by="$po->creator?->name" :created-at="$po->created_at?->format('Y-m-d H:i:s')"
        :updated-by="$po->approver?->name ?? $po->creator?->name" :updated-at="$po->updated_at?->format('Y-m-d H:i:s')">
            <x-dl::form.row :cols="3">
                <x-dl::field.display label="Document No." :value="raw($po->doc_no ?? '(otomatis)')" />
                <x-dl::field.date name="doc_date" label="Tanggal" :value="$d('doc_date')" required :error="$e('doc_date')" />
                <x-dl::field.select name="supplier_id" label="Supplier" :options="$suppliers" :value="$v('supplier_id')" required :error="$e('supplier_id')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="3">
                <x-dl::field.number name="payment_term_days" label="Payment Term (days)" :min="0" :max="365" :value="$v('payment_term_days') ?? ''" :error="$e('payment_term_days')" hint="0 = tunai. Terisi dari Pemasok." />
                <x-dl::field.date name="need_date" label="Kirim tgl" :value="$d('need_date')" :error="$e('need_date')" />
                <x-dl::field.text name="ship_to" label="Kirim ke" :value="$v('ship_to')" maxlength="150" :error="$e('ship_to')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="3">
                <x-dl::field.decimal name="vat_pct" label="PPN (%)" :decimals="2" :value="$v('vat_pct') ?? 0" :error="$e('vat_pct')" hint="0 = tanpa PPN." />
                <x-dl::field.text name="note" label="Catatan" :value="$v('note')" maxlength="255" :error="$e('note')" />
            </x-dl::form.row>
        <x-slot:detail>
            <x-dl::lines name="lines" title="Baris" add-label="+ Tambah Barang" :rows="$rows" :min-rows="$locked ? 0 : 1" :readonly="$locked" :row-state="true" :columns="$columns" />
            @if ($po->exists)
                <p class="dl-note">Subtotal {{ \Devlib\Locale::number($po->subtotal(), 2) }} · PPN {{ \Devlib\Locale::number($po->vat(), 2) }} · <b>Total {{ \Devlib\Locale::number($po->total(), 2) }}</b> (dari data tersimpan).</p>
            @endif
        </x-slot:detail>
    </x-dl::form>
    @unless ($locked)
        <script>
            (() => {
                const terms = @json($terms), sel = document.querySelector('[name=supplier_id]'), term = document.querySelector('[name=payment_term_days]');
                if (!sel || !term) return;
                sel.addEventListener('change', () => {
                    if (terms[sel.value] === undefined) return;
                    term.value = terms[sel.value];
                    term.dispatchEvent(new Event('input', {bubbles: true}));
                });
            })();
        </script>
    @endunless
@endsection
