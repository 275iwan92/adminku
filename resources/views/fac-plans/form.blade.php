{{-- REMARK
 Tujuan       : Form Jadwal perawatan berkala (Single Form kind=master): judul, aset ATAU gedung/ruang, periode, tanggal mulai, terakhir dikerjakan (otomatis maju saat tiket disetujui), vendor pelaksana (kosong = internal), catatan. Menampilkan jatuh tempo berikutnya dan tombol Buat Tiket.
 Sumber hulu  : FacPlanController (plan, over, assets, places, vendors).
 Input        : plan, over, assets, places, vendors.
 Output       : POST fac-plans.store (baru) / PUT fac-plans.update (ubah, Aktifkan, Nonaktifkan).
 Dipakai oleh : routes fac-plans.create, fac-plans.show.
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Jadwal Perawatan'])
@section('body')
    @php
        use App\Services\FacCare;
        $isNew = ! $plan->exists;
        $u = auth()->user();
        $v = fn (string $k) => old($k, $plan->{$k});
        $d = fn (string $k) => ($x = old($k, $plan->{$k})) ? \Illuminate\Support\Carbon::parse($x)->format('Y-m-d') : null;
        $e = fn (string $k) => $errors->first($k) ?: null;
    @endphp
    @include('partials.flash')
    @if (! $isNew && $plan->is_active)
        <x-dl::alert :tone="['aman' => 'info', 'segera' => 'warn', 'terlambat' => 'danger'][$over['status']]" :message="raw('Berikutnya '.$over['due']->format('d-m-Y').' ('.$over['due_text'].') · '.$over['label'])" />
        @if ($u->canWrite('FAT'))
            <x-dl::action-bar align="start">
                <x-dl::button class="sstock-btn-teal" variant="secondary" icon="fa-clipboard-list" label="Buat Tiket dari Jadwal" :href="route('fac-orders.create', ['plan' => $plan->id])" />
            </x-dl::action-bar>
        @endif
    @endif
    <x-dl::form :title="$isNew ? 'Jadwal Perawatan · Baru' : 'Jadwal Perawatan · '.raw($plan->title)" :subtitle="$isNew ? null : raw($plan->targetText())" panel="top" kind="master"
        :status="$isNew ? 'new' : ($plan->is_active ? 'active' : 'inactive')" :back="route('fac-plans.index')" :new-url="route('fac-plans.create')"
        :action="$isNew ? route('fac-plans.store') : route('fac-plans.update', $plan)" :hidden="$isNew ? [] : ['_method' => 'PUT']"
        :created-at="$plan->created_at?->format('Y-m-d H:i:s')" :updated-at="$plan->updated_at?->format('Y-m-d H:i:s')">
        <x-dl::form.row :cols="2">
            <x-dl::field.text name="title" label="Judul jadwal" :value="$v('title')" required maxlength="150" :error="$e('title')" hint="Mis. Cek panel listrik, Kuras toren air." />
            <x-dl::field.select name="period" label="Periode" :options="FacCare::PERIODS" :value="$v('period')" required :error="$e('period')" />
        </x-dl::form.row>
        <x-dl::form.row :cols="2">
            <x-dl::field.select name="asset_id" label="Aset" :options="['' => '(jadwal per gedung / ruang)'] + $assets" :value="$v('asset_id')" :error="$e('asset_id')" />
            <x-dl::field.select name="place_id" label="Gedung / ruang" :options="['' => '(jadwal per aset)'] + $places" :value="$v('place_id')" :error="$e('place_id')" hint="Pilih salah satu: aset atau gedung / ruang." />
        </x-dl::form.row>
        <x-dl::form.row :cols="3">
            <x-dl::field.date name="start_at" label="Tanggal mulai" :value="$d('start_at')" required :error="$e('start_at')" />
            <x-dl::field.date name="last_done_at" label="Terakhir dikerjakan" :value="$d('last_done_at')" :error="$e('last_done_at')" hint="Maju otomatis saat tiket dari jadwal ini disetujui." />
            <x-dl::field.select name="vendor_id" label="Vendor pelaksana" :options="['' => '(teknisi internal)'] + $vendors" :value="$v('vendor_id')" :error="$e('vendor_id')" />
        </x-dl::form.row>
        <x-dl::form.row>
            <x-dl::field.text name="note" label="Catatan" :value="$v('note')" maxlength="255" :error="$e('note')" />
        </x-dl::form.row>
    </x-dl::form>
@endsection
