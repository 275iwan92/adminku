{{-- REMARK
 Tujuan       : Form Tahap rekrutmen (Single Form kind=master): nama dan urutan; tahap awal / akhir hanya-baca.
 Sumber hulu  : RecruitStepController (step).
 Input        : step.
 Output       : POST recruit-steps.store (baru) / PUT recruit-steps.update (ubah, Aktifkan, Nonaktifkan).
 Dipakai oleh : routes recruit-steps.create, recruit-steps.show.
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Tahap Rekrutmen'])
@section('body')
    @php
        $isNew = ! $step->exists;
        $v = fn (string $k) => old($k, $step->{$k});
        $e = fn (string $k) => $errors->first($k) ?: null;
        $fixed = $step->exists && $step->isFixed();
    @endphp
    @include('partials.flash')
    <x-dl::form :title="$isNew ? 'Tahap · Baru' : 'Tahap · '.raw($step->name)" panel="top" kind="master"
        :status="$isNew ? 'new' : ($step->is_active ? 'active' : 'inactive')" :back="route('recruit-steps.index')" :new-url="route('recruit-steps.create')"
        :action="$isNew ? route('recruit-steps.store') : route('recruit-steps.update', $step)" :hidden="$isNew ? [] : ['_method' => 'PUT']"
        :created-at="$step->created_at?->format('Y-m-d H:i:s')" :updated-at="$step->updated_at?->format('Y-m-d H:i:s')">
        <x-dl::form.row :cols="3">
            <x-dl::field.text name="name" label="Nama Tahap" :value="$v('name')" required :readonly="$fixed" maxlength="60" :error="$e('name')" :hint="$fixed ? 'Tahap awal dan akhir tetap.' : 'Mis. Interview 1, Tes, TTD penawaran.'" />
            <x-dl::field.number name="sort" label="Urutan" :value="$v('sort')" required :readonly="$fixed" :error="$e('sort')" hint="Angka kecil lebih dulu. Daftar selalu pertama, Diterima selalu terakhir." />
            @unless ($fixed)
                <x-dl::field.toggle name="needs_schedule" label="Berjadwal" on-label="Ya" off-label="Tidak" :value="session()->hasOldInput() ? (bool) old('needs_schedule') : $step->needs_schedule" hint="Ya: memindahkan kartu ke tahap ini memunculkan pop-up tanggal dan jam (wajib)." />
            @endunless
            @unless ($isNew)
                <x-dl::field.display label="Kandidat di tahap ini" :value="(string) $step->candidates_count" />
            @endunless
        </x-dl::form.row>
    </x-dl::form>
@endsection
