{{-- REMARK
 Tujuan       : Form penerbitan Kedisiplinan (Single Form kind=master): karyawan, jenis (SP 1 / SP 2 / SP 3 / PHK), tanggal terbit, berlaku s/d (SP; kosong = +6 bulan) atau tanggal efektif (PHK), pelanggaran, rujukan aturan, penandatangan (bawaan dari penerbitan terakhir).
                Jenis yang sesuai urutan disarankan (bukan dipaksa). Nomor surat dibuat saat Terbitkan.
 Sumber hulu  : DisciplineController@create (discipline, employees, employee, suggest, inForce).
 Input        : discipline, employees, employee, suggest, inForce.
 Output       : POST disciplines.store.
 Dipakai oleh : route disciplines.create (tombol Terbitkan SP di kartu Karyawan membawa ?employee=).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Terbitkan Surat'])
@section('body')
    @php
        $v = fn (string $k) => old($k, $discipline->{$k});
        $d = fn (string $k) => old($k, $discipline->{$k}?->format('Y-m-d'));
        $e = fn (string $k) => $errors->first($k) ?: null;
        $types = \App\Models\Discipline::TYPES;
    @endphp
    @include('partials.flash')
    @if ($employee)
        <x-dl::alert :tone="$inForce ? 'warning' : 'info'" :message="raw($inForce ? 'SP berlaku untuk '.e($employee->name).': '.e($types[$inForce->type]).' ('.e($inForce->doc_no).', s/d '.e($inForce->until_on->format('d-m-Y')).'). Urutan yang sesuai: '.e($types[$suggest]).'.' : e($employee->name).' belum punya SP yang berlaku. Urutan yang sesuai: '.e($types[$suggest]).'.')" />
    @endif
    <x-dl::form title="Terbitkan Surat" panel="top" kind="master" status="new" :back="route('disciplines.index')" :action="route('disciplines.store')" :hide="['new']">
        <x-dl::form.row :cols="3">
            <x-dl::field.select name="employee_id" label="Karyawan" :options="$employees" :value="$v('employee_id')" required :error="$e('employee_id')" hint="Hanya karyawan aktif. Memilih dari kartu Karyawan mengisi ini sekaligus menyarankan jenis." />
            <x-dl::field.select name="type" label="Jenis" :options="$types" :value="$v('type')" required :error="$e('type')" hint="Disarankan menurut SP yang berlaku; boleh memilih lain (loncat memberi peringatan)." />
            <x-dl::field.date name="issued_on" label="Tanggal Terbit" :value="$d('issued_on')" required :error="$e('issued_on')" />
        </x-dl::form.row>
        <x-dl::form.row :cols="3">
            <x-dl::field.date name="until_on" label="Berlaku s/d (PHK: Tanggal Efektif)" :value="$d('until_on')" :error="$e('until_on')" hint="SP: kosong = 6 bulan sejak terbit. PHK: wajib diisi (tanggal efektif)." />
        </x-dl::form.row>
        <x-dl::form.row>
            <x-dl::field.text name="reason" label="Pelanggaran / Alasan" :value="$v('reason')" required maxlength="500" :error="$e('reason')" />
        </x-dl::form.row>
        <x-dl::form.row>
            <x-dl::field.text name="rule_ref" label="Rujukan Aturan" :value="$v('rule_ref')" maxlength="255" :error="$e('rule_ref')" hint="Mis. Peraturan Perusahaan pasal ... (tercetak di surat)." />
        </x-dl::form.row>
        <x-dl::form.row :cols="2">
            <x-dl::field.text name="signer_name" label="Nama Penandatangan" :value="$v('signer_name')" required maxlength="100" :error="$e('signer_name')" hint="Bawaan dari penerbitan terakhir." />
            <x-dl::field.text name="signer_title" label="Jabatan Penandatangan" :value="$v('signer_title')" required maxlength="100" :error="$e('signer_title')" />
        </x-dl::form.row>
    </x-dl::form>
    <p class="dl-note">Simpan menerbitkan surat dan memberi nomor otomatis. Surat yang sudah terbit tidak diubah; bila keliru, Cabut dengan alasan lalu terbitkan ulang.</p>
@endsection
