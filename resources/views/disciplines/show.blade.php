{{-- REMARK
 Tujuan       : Detail penerbitan Kedisiplinan (hanya baca): nomor, jenis, karyawan, tanggal, alasan, penandatangan, status; tombol Cetak, Cabut dengan alasan (bagi pemegang hak tulis), dan untuk PHK tautan ke Karyawan untuk Nonaktifkan (alasan PHK terisi).
 Sumber hulu  : DisciplineController@show (discipline, state).
 Input        : discipline, state.
 Output       : tampilan; POST disciplines.revoke.
 Dipakai oleh : route disciplines.show (klik 2x baris daftar).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Penerbitan'])
@section('body')
    @php $u = auth()->user(); $types = \App\Models\Discipline::TYPES; @endphp
    @include('partials.flash')
    <x-dl2::box>
        <x-dl2::action-bar align="start">
            <x-dl2::button class="sstock-btn-slate" variant="secondary" icon="back" label="Kembali" :href="route('disciplines.index')" />
            <x-dl2::button class="sstock-btn-teal" variant="primary" icon="fa-print" label="Cetak" :href="route('disciplines.print', $discipline)" />
            @if ($discipline->type === 'phk' && $discipline->status === 'issued' && $discipline->employee->is_active && $u->canAccess('HRE'))
                <x-dl2::button class="sstock-btn-amber" variant="secondary" icon="fa-user-slash" label="Nonaktifkan karyawan" :href="route('employees.show', ['employee' => $discipline->employee, 'exit' => 'laid_off'])" />
            @endif
        </x-dl2::action-bar>
        <x-dl2::form.row :cols="3">
            <x-dl2::field.display label="No. Surat" :value="raw($discipline->doc_no)" />
            <x-dl2::field.display label="Jenis" :value="raw($types[$discipline->type])" />
            <x-dl2::field.display label="Status" :value="raw($state)" />
        </x-dl2::form.row>
        <x-dl2::form.row :cols="3">
            <x-dl2::field.display label="Karyawan" :value="raw($discipline->employee->code.' · '.$discipline->employee->name.' · '.$discipline->employee->department->name)" />
            <x-dl2::field.display label="Tanggal Terbit" :value="raw($discipline->issued_on->format('d-m-Y'))" />
            <x-dl2::field.display :label="$discipline->type === 'phk' ? 'Tanggal Efektif' : 'Berlaku s/d'" :value="raw($discipline->until_on?->format('d-m-Y') ?? '-')" />
        </x-dl2::form.row>
        <x-dl2::form.row>
            <x-dl2::field.display label="Pelanggaran / Alasan" :value="raw($discipline->reason.($discipline->rule_ref ? ' ('.$discipline->rule_ref.')' : ''))" />
        </x-dl2::form.row>
        <x-dl2::form.row :cols="2">
            <x-dl2::field.display label="Penandatangan" :value="raw($discipline->signer_name.' · '.$discipline->signer_title)" />
            @if ($discipline->status === 'revoked')
                <x-dl2::field.display label="Dicabut" :value="raw($discipline->revoked_at->format('d-m-Y').' · '.$discipline->revoke_reason)" />
            @endif
        </x-dl2::form.row>
        @if ($discipline->status === 'issued' && $u->canWrite('HRD'))
            <form method="POST" action="{{ route('disciplines.revoke', $discipline) }}">
                @csrf
                <x-dl2::form.row :cols="2">
                    <x-dl2::field.text name="revoke_reason" label="Alasan Pencabutan" :value="old('revoke_reason')" maxlength="255" :error="$errors->first('revoke_reason') ?: null" hint="Surat tidak dihapus dan nomornya tidak dipakai ulang." />
                </x-dl2::form.row>
                <x-dl2::button class="sstock-btn-red" variant="secondary" icon="fa-ban" label="Cabut surat" type="submit" />
            </form>
        @endif
    </x-dl2::box>
@endsection
