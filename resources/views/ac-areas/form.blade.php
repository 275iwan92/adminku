{{-- REMARK
 Tujuan       : Form master Area / Gedung AC (Single Form kind=master): kode (kosong = otomatis dari nama), nama, denah unggahan (jpg / png), daftar ruang di area ini.
 Sumber hulu  : AcAreaController (area).
 Input        : area (beserta rooms saat ubah).
 Output       : POST ac-areas.store (baru) / PUT ac-areas.update (ubah, Aktifkan, Nonaktifkan), multipart.
 Dipakai oleh : routes ac-areas.create, ac-areas.show.
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Area dan Gedung AC'])
@section('body')
    @php
        $isNew = ! $area->exists;
        $v = fn (string $k) => old($k, $area->{$k});
        $e = fn (string $k) => $errors->first($k) ?: null;
        $u = auth()->user();
    @endphp
    @include('partials.flash')
    <x-dl::form :title="$isNew ? 'Area AC · Baru' : 'Area AC · '.raw($area->code)" :subtitle="$isNew ? null : raw($area->name)" panel="top" kind="master"
        :status="$isNew ? 'new' : ($area->is_active ? 'active' : 'inactive')" :back="route('ac-areas.index')" :new-url="route('ac-areas.create')"
        :action="$isNew ? route('ac-areas.store') : route('ac-areas.update', $area)" :hidden="$isNew ? [] : ['_method' => 'PUT']" enctype="multipart/form-data"
        :created-at="$area->created_at?->format('Y-m-d H:i:s')" :updated-at="$area->updated_at?->format('Y-m-d H:i:s')">
        <x-dl::form.row :cols="3">
            <x-dl::field.text name="name" label="Nama Area / Gedung" :value="$v('name')" required maxlength="100" :error="$e('name')" hint="Mis. Kantor Lt. 1, Gudang, Workshop." />
            <x-dl::field.text name="code" label="Kode Area" :value="$v('code')" code :case-toggle="false" maxlength="20" :error="$e('code')" hint="Kosong = otomatis dari nama." />
        </x-dl::form.row>
        <x-dl::form.row>
            <x-dl::field.upload name="plan" label="Denah (gambar)" accept=".jpg,.jpeg,.png,image/jpeg,image/png" :error="$e('plan')"
                :value="$area->plan_path ? ['url' => route('ac-areas.plan', $area), 'name' => $area->plan_name] : null"
                hint="Opsional. Gambar JPG atau PNG, paling besar 5 MB; titik AC mengikuti persen posisi di gambar." />
        </x-dl::form.row>
        @if ($area->plan_path)
            <x-dl::form.row>
                <x-dl::field.toggle name="remove_plan" label="Hapus Denah Unggahan" on-label="Hapus saat disimpan" off-label="Pertahankan" :value="(bool) old('remove_plan')" hint="Area kembali tanpa gambar denah. Gambar lama dibuang dari penyimpanan." />
            </x-dl::form.row>
        @endif
    </x-dl::form>
    @unless ($isNew)
        <x-dl::divider label="Ruang di area ini" />
        @if ($area->rooms->isNotEmpty())
            <div class="dl-table-wrap dl-table--stack">
                <table class="dl-table is-striped">
                    <thead><tr><th>Kode</th><th>Ruang</th><th>PIC</th><th>Status</th></tr></thead>
                    <tbody>
                        @foreach ($area->rooms->sortBy('code') as $r)
                            <tr>
                                <td data-label="Kode"><b>{{ $r->code }}</b></td>
                                <td data-label="Ruang">@if ($u->canAccess('GAR'))<a href="{{ route('ac-rooms.show', $r) }}">{{ $r->name }}</a>@else{{ $r->name }}@endif</td>
                                <td data-label="PIC">{{ $r->pic_name ?: '—' }}</td>
                                <td data-label="Status"><x-dl::badge :tone="$r->is_active ? 'success' : 'neutral'" :label="$r->is_active ? 'Aktif' : 'Nonaktif'" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="dl-note">Belum ada ruang di area ini. Tambahkan di Ruang AC.</p>
        @endif
    @endunless
@endsection
