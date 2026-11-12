{{-- REMARK
 Tujuan       : Form Perintah Kerja perawatan (Single Form kind=document): laporan (judul, aset atau gedung/ruang, prioritas, uraian), pelaksanaan (vendor atau teknisi internal, tanggal selesai, biaya tenaga dan material, catatan), foto sebelum dan sesudah.
                Siklus tombol DevLib: Draft (Save, Confirm, Delete) > Approval (Supervisor: Approve / Reject; pembuat: Cancel) > Approved (= Selesai) | Rejected | Cancelled. Terkunci setelah Confirm.
 Sumber hulu  : FacOrderController (order, log, assets, places, vendors).
 Input        : order, log, assets, places, vendors.
 Output       : POST fac-orders.store (baru = draf) / PUT fac-orders.update (save, confirm, approve, reject, cancel, delete), multipart.
 Dipakai oleh : routes fac-orders.create, fac-orders.show.
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Perintah Kerja'])
@section('body')
    @php
        use App\Services\FacCare;
        $isNew = ! $order->exists;
        $me = auth()->user();
        $locked = ! $isNew && $order->status !== 'draft';
        $mine = $isNew || $me->id === $order->created_by;
        $v = fn (string $k) => old($k, $order->{$k});
        $d = fn (string $k) => ($x = old($k, $order->{$k})) ? \Illuminate\Support\Carbon::parse($x)->format('Y-m-d') : null;
        $e = fn (string $k) => $errors->first($k) ?: null;
        $photo = fn (string $k) => $order->{"photo_{$k}_path"} ? ['url' => route('fac-orders.file', [$order, $k]), 'name' => $order->{"photo_{$k}_name"}] : null;
        $handler = old('handler', $order->handler);
    @endphp
    @include('partials.flash')
    @if ($order->exists && $order->status === 'rejected')
        <x-dl2::alert tone="warn" :message="raw('Ditolak oleh '.($order->approver?->name ?? '-').'. Alasan: '.$order->reason.'.')" />
    @endif
    <x-dl2::form :title="'Perintah Kerja · '.($isNew ? 'Baru' : raw($order->number()))" :subtitle="$isNew ? null : raw($order->targetText())" panel="top" kind="document" :status="$isNew ? 'new' : $order->status"
        :back="route('fac-orders.index')" :new-url="route('fac-orders.create')" enctype="multipart/form-data"
        :action="$isNew ? route('fac-orders.store') : route('fac-orders.update', $order)" :method="$isNew ? 'post' : 'put'"
        :pipeline="true" :approval-levels="1" :approval-at="1" :can-approve="$me->canApprove() && ! $mine"
        :hide="['hold', 'email', 'print', 'duplicate']" :log="$log"
        :created-by="$order->creator?->name" :created-at="$order->created_at?->format('Y-m-d H:i:s')"
        :updated-by="$order->approver?->name ?? $order->creator?->name" :updated-at="$order->updated_at?->format('Y-m-d H:i:s')">
        @if ($order->plan_id)<input type="hidden" name="plan_id" value="{{ $order->plan_id }}">@endif
        <x-dl2::form.row :cols="3">
            <x-dl2::field.display label="No. WO" :value="raw($order->no ?? '(otomatis saat Confirm)')" />
            <x-dl2::field.date name="reported_at" label="Tanggal lapor" :value="$d('reported_at')" required :readonly="$locked" :error="$e('reported_at')" />
            <x-dl2::field.select name="priority" label="Prioritas" :options="FacCare::PRIORITIES" :value="$v('priority')" required :readonly="$locked" :error="$e('priority')" />
        </x-dl2::form.row>
        <x-dl2::form.row>
            <x-dl2::field.text name="title" label="Judul / kerusakan" :value="$v('title')" required maxlength="150" :readonly="$locked" :error="$e('title')" hint="Mis. AC bocor, lampu lorong mati, pintu macet." />
        </x-dl2::form.row>
        <x-dl2::form.row :cols="2">
            <x-dl2::field.select name="asset_id" label="Aset" :options="['' => '(bukan satu aset)'] + $assets" :value="$v('asset_id')" :readonly="$locked" :error="$e('asset_id')" />
            <x-dl2::field.select name="place_id" label="Gedung / ruang" :options="['' => '(ikut lokasi aset)'] + $places" :value="$v('place_id')" :readonly="$locked" :error="$e('place_id')" hint="Wajib bila tidak memilih aset." />
        </x-dl2::form.row>
        <x-dl2::form.row>
            <x-dl2::field.text name="description" label="Uraian" :value="$v('description')" maxlength="2000" :readonly="$locked" :error="$e('description')" />
        </x-dl2::form.row>
        <x-dl2::form.row :cols="3">
            <x-dl2::field.select name="handler" label="Dikerjakan oleh" :options="['vendor' => 'Vendor', 'internal' => 'Teknisi internal']" :value="$handler" required :readonly="$locked" :error="$e('handler')" />
            <x-dl2::field.select name="vendor_id" label="Vendor" :options="['' => '(pilih)'] + $vendors" :value="$v('vendor_id')" :readonly="$locked" :error="$e('vendor_id')" hint="Wajib bila dikerjakan vendor. Master di Vendor AC." />
            <x-dl2::field.text name="technician" label="Teknisi internal" :value="$v('technician')" maxlength="100" :readonly="$locked" :error="$e('technician')" />
        </x-dl2::form.row>
        <x-dl2::form.row :cols="3">
            <x-dl2::field.date name="finished_at" label="Tanggal selesai" :value="$d('finished_at')" :readonly="$locked" :error="$e('finished_at')" hint="Wajib diisi saat Confirm." />
            <x-dl2::field.decimal name="labor_cost" label="Biaya tenaga (Rp)" :decimals="2" :value="$v('labor_cost')" :readonly="$locked" :error="$e('labor_cost')" />
            <x-dl2::field.decimal name="material_cost" label="Biaya material (Rp)" :decimals="2" :value="$v('material_cost')" :readonly="$locked" :error="$e('material_cost')" />
        </x-dl2::form.row>
        <x-dl2::form.row :cols="2">
            <x-dl2::field.upload name="photo_before" label="Foto sebelum" accept=".jpg,.jpeg,.png,image/jpeg,image/png" :preview="true" :value="$photo('before')" :readonly="$locked" :error="$e('photo_before')" />
            <x-dl2::field.upload name="photo_after" label="Foto sesudah" accept=".jpg,.jpeg,.png,image/jpeg,image/png" :preview="true" :value="$photo('after')" :readonly="$locked" :error="$e('photo_after')" />
        </x-dl2::form.row>
        <x-dl2::form.row>
            <x-dl2::field.text name="note" label="Catatan" :value="$v('note')" maxlength="255" :readonly="$locked" :error="$e('note')" />
        </x-dl2::form.row>
        @if ($order->exists)
            <p class="ak-note">Total biaya: <b>Rp {{ number_format($order->total(), 0, ',', '.') }}</b> (tenaga + material). Biaya masuk laporan setelah Perintah Kerja disetujui Supervisor.</p>
        @endif
    </x-dl2::form>
@endsection
