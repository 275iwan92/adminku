{{-- REMARK
 Tujuan       : Form master Satuan (Single Form kind=master): kode (tetap setelah disimpan), nama, dan tabel Conversions (1 satuan ini = faktor x satuan lain, berlaku untuk semua item).
 Sumber hulu  : UnitController (unit, targets = satuan tujuan, conversions = baris konversi).
 Input        : unit, targets, conversions.
 Output       : POST units.store (baru) / PUT units.update (ubah nama + konversi, Activate, Deactivate).
 Dipakai oleh : routes units.create, units.show.
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Unit'])
@section('body')
    @php
        $isNew = ! $unit->exists;
        $v = fn (string $k) => old($k, $unit->{$k});
        $e = fn (string $k) => $errors->first($k) ?: null;
        $code = $unit->code ?: 'unit';
    @endphp
    @include('partials.flash')
    <x-dl::form :title="$isNew ? 'Satuan · Baru' : 'Satuan · '.raw($unit->code)" :subtitle="$isNew ? null : raw($unit->name)" panel="top" kind="master"
        :status="$isNew ? 'new' : ($unit->is_active ? 'active' : 'inactive')" :back="route('units.index')" :new-url="route('units.create')"
        :action="$isNew ? route('units.store') : route('units.update', $unit)" :hidden="$isNew ? [] : ['_method' => 'PUT']"
        :created-at="$unit->created_at?->format('Y-m-d H:i:s')" :updated-at="$unit->updated_at?->format('Y-m-d H:i:s')">
        <x-dl::form.row :cols="3">
            <x-dl::field.text name="code" label="Unit Code" :value="$v('code')" code :case-toggle="false" required :readonly="! $isNew" maxlength="20" :error="$e('code')" hint="Short, lowercase (pcs, box, kg). Cannot be changed after saving." />
            <x-dl::field.text name="name" label="Unit Name" :value="$v('name')" required maxlength="100" :error="$e('name')" />
            <x-dl::field.select name="kind" label="Type" :options="\App\Models\Unit::KINDS" :value="$v('kind') ?: 'count'" required :error="$e('kind')" tooltip="Sets the colour of the unit in lists (for example weight units share one colour)." />
        </x-dl::form.row>
        @unless ($isNew)
            <x-dl::form.row :cols="3">
                <x-dl::field.display label="Items using it" :value="(string) $unit->items_count" />
            </x-dl::form.row>
        @endunless
        <x-slot:detail>
            <x-dl::lines name="conversions" :title="'Konversi · 1 '.$code.' ='" add-label="+ Add Conversion" :rows="$conversions" :min-rows="0" :row-state="true"
                :columns="[
                    ['key' => 'factor', 'label' => 'Factor', 'type' => 'number', 'required' => true, 'width' => '160px'],
                    ['key' => 'to_unit', 'label' => 'Unit', 'type' => 'select', 'options' => $targets, 'required' => true, 'width' => '280px'],
                ]" />
            <p class="dl-note">Berlaku sama untuk semua barang yang memakai satuan ini. Arah sebaliknya dihitung otomatis (1 / faktor), jadi isi tiap pasangan satu kali saja.</p>
        </x-slot:detail>
    </x-dl::form>
@endsection
