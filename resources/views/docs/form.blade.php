{{-- REMARK
 Tujuan       : Form dokumen stok (Master-Detail): header menurut jenis + tabel baris item (x-dl::lines). Semua jenis: Draft (belum menyentuh stok, boleh Delete) > Confirm (diproses ke stok; penyesuaian: diajukan lalu Approve Supervisor) > Cancel (pengguna mengajukan dengan alasan, Supervisor menyetujui; ditolak bila stok tidak cukup, koreksi lewat Stock Adjustment).
 Sumber hulu  : StockDocumentController (t, doc, lines, suppliers, log, fefo), Lookup product_in / item_out / product_opn / item_adj (ItemLookups).
 Input        : t, doc, lines, suppliers, log, cancelProblem (hasil hitungan: stok masih cukup untuk membatalkan atau tidak).
 Output       : POST docs.store (baru = draf) / PUT docs.update (save, confirm, approve, reject, cancel, delete).
 Dipakai oleh : routes docs.create, docs.show.
 Status       : Aktif.
--}}
@extends('layout', ['title' => $t->label()])
@section('body')
    @php
        use App\Enums\StockDocumentType as T;
        $sup = auth()->user()->isSupervisor();
        $isNew = ! $doc->exists;
        $isAdj = $t === T::Adjustment;
        $pendingCancel = ! $isNew && $doc->status === 'cancel_pending';
        $status = $isNew ? 'new' : ($pendingCancel ? 'approval' : $doc->status);   // pengajuan pembatalan memakai tahap persetujuan DevLib (Approve / Reject untuk Supervisor)
        $locked = ! $isNew && $doc->status !== 'draft';
        $v = fn (string $k, $default = null) => old($k, $doc->{$k} ?? $default);
        $e = fn (string $k) => $errors->first($k) ?: null;
        $rows = $lines;
        // Penerimaan / Saldo Awal memilih produk + tanggal kedaluwarsa (batch dibuat otomatis); Pengeluaran / Penyesuaian memilih batch.
        $lookup = ['product_in', 'item_out', 'product_opn', 'item_adj'][array_search($t, [T::Receiving, T::Issuance, T::Opening, T::Adjustment], true)];
        $byProduct = in_array($t, [T::Receiving, T::Opening], true);
        $item = ['key' => $byProduct ? 'item_id' : 'batch_id', 'label' => 'Item', 'type' => 'lookup', 'lookup' => $lookup, 'required' => true, 'width' => '300px'];
        $expiry = ['key' => 'expiry_date', 'label' => 'Expiry Date', 'type' => 'date', 'width' => '150px'];
        $unit = ['key' => 'unit', 'label' => 'Unit', 'type' => 'display', 'width' => '80px'];
        $note = ['key' => 'note', 'label' => 'Line Note', 'width' => '200px'];
        $columns = match ($t) {
            T::Receiving => [$item, $expiry,
                ['key' => 'qty', 'label' => 'Qty', 'type' => 'number', 'required' => true, 'width' => '110px'],
                ['key' => 'unit', 'label' => 'Unit', 'type' => 'select', 'options' => $units, 'required' => true, 'width' => '200px'],
                ['key' => 'unit_cost', 'label' => 'Purchase Price / Unit', 'type' => 'money', 'required' => true, 'width' => '150px'],
                ['key' => 'subtotal', 'label' => 'Subtotal', 'type' => 'money', 'formula' => 'qty * unit_cost', 'sum' => true, 'width' => '150px'],
                ['key' => 'stock_qty', 'label' => '= In stock unit', 'type' => 'display', 'width' => '120px'], $note],
            T::Issuance => [$item,
                ['key' => 'available', 'label' => 'Available', 'type' => 'display', 'width' => '100px'],
                ['key' => 'qty', 'label' => 'Qty', 'type' => 'number', 'required' => true, 'width' => '110px'], $unit, $note],
            T::Opening => [$item, $expiry, $unit,
                ['key' => 'qty', 'label' => 'Opening Qty', 'type' => 'number', 'required' => true, 'sum' => true, 'width' => '120px'],
                ['key' => 'unit_cost', 'label' => 'Unit Value', 'type' => 'money', 'required' => true, 'width' => '140px'],
                ['key' => 'subtotal', 'label' => 'Valuation', 'type' => 'money', 'formula' => 'qty * unit_cost', 'sum' => true, 'width' => '150px'], $note],
            T::Adjustment => [$item, $unit,
                ['key' => 'system_qty', 'label' => 'System Qty', 'type' => 'display', 'width' => '110px'],
                ['key' => 'counted_qty', 'label' => 'Counted Qty (physical)', 'type' => 'number', 'required' => true, 'width' => '150px'],
                ['key' => 'diff', 'label' => 'Difference', 'type' => 'number', 'formula' => 'counted_qty - system_qty', 'width' => '110px'], $note],
        };
        $title = $t->label().' · '.($isNew ? 'Baru' : raw($doc->doc_no));
    @endphp
    @include('partials.flash')
    @if (! empty($cancelProblem))
        <x-dl::alert tone="warn" :message="raw($cancelProblem)" />
    @endif
    @if ($t === T::Receiving)
        @include('docs.pack-units')
        @if (! $locked)
            @include('docs.conversion-modal')
        @endif
    @endif
    @if (! empty($fefo))
        <x-dl::alert tone="warn" :message="raw(implode(' ', $fefo))" />
    @endif
    @if ($pendingCancel)
        <x-dl::alert tone="warn" :message="raw('Pembatalan diajukan oleh '.($doc->cancelRequester?->name ?? '-').' dan menunggu persetujuan Supervisor. Alasan: '.$doc->cancel_reason.'. Stok belum berubah.')" />
    @endif
    <x-dl::form :title="$title" :subtitle="$isNew ? null : raw($doc->supplier?->name ?? $doc->recipient ?? $doc->reason)" panel="top" kind="document" :status="$status"
        :back="route('docs.index', $t->slug())" :new-url="route('docs.create', $t->slug())"
        :action="$isNew ? route('docs.store', $t->slug()) : route('docs.update', [$t->slug(), $doc])" :hidden="$isNew ? [] : ['_method' => 'PUT']"
        :pipeline="true" :approval-levels="$isAdj || $pendingCancel ? 1 : 0" :approval-at="1" :can-approve="$sup"
        :hide="array_merge(['hold', 'email', 'duplicate'], $doc->reversal_of_document_id ? ['cancel'] : [])" :print-url="$isNew ? null : route('docs.print', [$t->slug(), $doc])" :log="$log"
        :created-by="$doc->creator?->name" :created-at="$doc->created_at?->format('Y-m-d H:i:s')"
        :updated-by="$doc->approver?->name ?? $doc->creator?->name" :updated-at="$doc->updated_at?->format('Y-m-d H:i:s')">
        <x-dl::form.row :cols="3">
            <x-dl::field.display label="Document No." :value="raw($doc->doc_no ?? '(otomatis)')" />
            @if ($t === T::Opening)
                <x-dl::field.date name="doc_at" label="Balance As Of (period start)" :value="$v('doc_at') ? \Illuminate\Support\Carbon::parse($v('doc_at'))->format('Y-m-d') : today()->format('Y-m-d')" required :error="$e('doc_at')" hint="Only for items without any stock movement yet." />
            @else
                <x-dl::field.display label="Date & Time" :value="raw($isNew ? 'Diisi saat disimpan' : \Devlib\Locale::stamp($doc->doc_at))" />
            @endif
            <x-dl::field.display label="Entered by" :value="raw($doc->creator?->name ?? auth()->user()->name)" />
        </x-dl::form.row>
        @if ($t === T::Receiving)
            <x-dl::form.row :cols="3">
                <x-dl::field.select name="supplier_id" label="Supplier" :options="$suppliers + ($doc->supplier ? [$doc->supplier_id => $doc->supplier->name] : [])" :value="$v('supplier_id')" required :add="$locked ? [] : \App\Support\QuickAdd::for('supplier')" :error="$e('supplier_id')" />
                <x-dl::field.text name="ref_no" label="Reference No. (delivery note / PO / invoice)" :value="$v('ref_no')" maxlength="60" :error="$e('ref_no')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="3">
                @include('docs.recipient-field')
                @include('docs.department-field')
            </x-dl::form.row>
        @elseif ($t === T::Issuance)
            <x-dl::form.row :cols="3">
                @include('docs.recipient-field')
                @include('docs.department-field')
                <x-dl::field.text name="reason" label="Purpose (maintenance / ATK ...)" :value="$v('reason')" maxlength="255" :error="$e('reason')" />
            </x-dl::form.row>
        @elseif ($isAdj)
            <x-dl::form.row :cols="3">
                <x-dl::field.text name="reason" label="Reason for adjustment / opname" :value="$v('reason', 'Opname stok')" required maxlength="255" :error="$e('reason')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="3">
                @include('docs.recipient-field')
                @include('docs.department-field')
            </x-dl::form.row>
        @endif
        <x-dl::form.row>
            <x-dl::field.text name="note" label="Notes" :value="$v('note')" multiline maxlength="1000" :error="$e('note')" />
        </x-dl::form.row>
        <x-slot:detail>
            <x-dl::lines name="lines" title="Items" add-label="+ Add Item" :rows="$rows" :min-rows="$locked ? 0 : 1" :readonly="$locked" :row-state="true" :columns="$columns" />
        </x-slot:detail>
    </x-dl::form>
@endsection
