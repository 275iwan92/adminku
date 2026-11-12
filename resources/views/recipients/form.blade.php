{{-- REMARK
 Tujuan       : Form master Penerima barang (Single Form kind=master): nama dan departemen bawaan (dipakai bila Issuance tidak memilih departemen).
 Sumber hulu  : RecipientController (recipient, departments).
 Input        : recipient, departments.
 Output       : POST recipients.store (baru) / PUT recipients.update (ubah, Activate, Deactivate).
 Dipakai oleh : routes recipients.create, recipients.show.
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Recipient'])
@section('body')
    @php
        $isNew = ! $recipient->exists;
        $v = fn (string $k) => old($k, $recipient->{$k});
        $e = fn (string $k) => $errors->first($k) ?: null;
        $deptOptions = $departments + ($recipient->department && ! isset($departments[$recipient->department_id]) ? [$recipient->department_id => $recipient->department->name] : []);
    @endphp
    @include('partials.flash')
    <x-dl2::form :title="$isNew ? 'Penerima · Baru' : 'Penerima · '.raw($recipient->name)" panel="top" kind="master"
        :status="$isNew ? 'new' : ($recipient->is_active ? 'active' : 'inactive')" :back="route('recipients.index')" :new-url="route('recipients.create')"
        :action="$isNew ? route('recipients.store') : route('recipients.update', $recipient)" :method="$isNew ? 'post' : 'put'"
        :created-at="$recipient->created_at?->format('Y-m-d H:i:s')" :updated-at="$recipient->updated_at?->format('Y-m-d H:i:s')">
        <x-dl2::form.row :cols="3">
            <x-dl2::field.text name="name" label="Recipient Name" :value="$v('name')" required maxlength="100" :error="$e('name')" />
            <x-dl2::field.select name="department_id" label="Default Department" :options="$deptOptions" :value="$v('department_id')" :add="\App\Support\QuickAdd::for('department')" :error="$e('department_id')" hint="Used in Issuance when no department is chosen." />
        </x-dl2::form.row>
        @unless ($isNew)
            <x-dl2::form.row :cols="3">
                <x-dl2::field.display label="Issuance Documents" :value="(string) $recipient->documents_count" />
            </x-dl2::form.row>
            @if ($recipient->employee)
                <x-dl2::form.row>
                    @if (auth()->user()->canAccess('HRE'))
                        <x-dl2::button icon="fa-id-card" :label="'Karyawan: '.$recipient->employee->code.' · '.$recipient->employee->name" :href="route('employees.show', $recipient->employee)" />
                    @else
                        <x-dl2::field.display label="Karyawan" :value="$recipient->employee->code.' · '.$recipient->employee->name" />
                    @endif
                </x-dl2::form.row>
            @elseif (auth()->user()->canWrite('HRE'))
                <x-dl2::form.row>
                    <x-dl2::button class="sstock-btn-teal" variant="primary" icon="fa-id-card" label="Jika penerima ini karyawan, klik di sini" :href="route('employees.create', ['penerima' => $recipient->id])" />
                </x-dl2::form.row>
            @endif
        @endunless
    </x-dl2::form>
@endsection
