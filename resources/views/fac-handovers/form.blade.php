{{-- REMARK
 Tujuan       : Form Serah Terima Aset (Single Form kind=document): jenis (pinjam / serah, bawa pulang, pengembalian, laporan hilang), aset (menampilkan posisi dan pemegang sekarang), penerima (karyawan atau departemen), tanggal, perkiraan kembali,
                tujuan / kronologi, kondisi saat keluar atau kembali, catatan kerusakan, foto lampiran. Siklus tombol DevLib: Draft > Approval (Supervisor: Approve / Reject; pembuat: Cancel) > Approved | Rejected | Cancelled.
 Sumber hulu  : FacHandoverController (h, log, assets, employees, departments, conditions).
 Input        : h, log, assets, employees, departments, conditions.
 Output       : POST fac-handovers.store (baru = draf) / PUT fac-handovers.update (save, confirm, approve, reject, cancel, delete), multipart.
 Dipakai oleh : routes fac-handovers.create, fac-handovers.show.
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Serah Terima Aset'])
@section('body')
    @php
        use App\Models\FacHandover;
        $isNew = ! $h->exists;
        $me = auth()->user();
        $locked = ! $isNew && $h->status !== 'draft';
        $mine = $isNew || $me->id === $h->created_by;
        $v = fn (string $k) => old($k, $h->{$k});
        $d = fn (string $k) => ($x = old($k, $h->{$k})) ? \Illuminate\Support\Carbon::parse($x)->format('Y-m-d') : null;
        $e = fn (string $k) => $errors->first($k) ?: null;
    @endphp
    @include('partials.flash')
    @if ($h->exists && $h->status === 'rejected')
        <x-dl2::alert tone="warn" :message="raw('Ditolak oleh '.($h->approver?->name ?? '-').'. Alasan: '.$h->reason.'.')" />
    @endif
    <x-dl2::form :title="'Serah Terima Aset · '.($isNew ? 'Baru' : raw($h->number()))" :subtitle="$isNew ? null : raw(FacHandover::TYPES[$h->type].' · '.$h->asset->asset_code)" panel="top" kind="document" :status="$isNew ? 'new' : $h->status"
        :back="route('fac-handovers.index')" :new-url="route('fac-handovers.create')" enctype="multipart/form-data"
        :action="$isNew ? route('fac-handovers.store') : route('fac-handovers.update', $h)" :method="$isNew ? 'post' : 'put'"
        :pipeline="true" :approval-levels="1" :approval-at="1" :can-approve="$me->canApprove() && ! $mine"
        :hide="['hold', 'email', 'print', 'duplicate']" :log="$log"
        :created-by="$h->creator?->name" :created-at="$h->created_at?->format('Y-m-d H:i:s')"
        :updated-by="$h->approver?->name ?? $h->creator?->name" :updated-at="$h->updated_at?->format('Y-m-d H:i:s')">
        <x-dl2::form.row :cols="3">
            <x-dl2::field.display label="No. ST" :value="raw($h->no ?? '(otomatis saat Confirm)')" />
            <x-dl2::field.date name="doc_date" label="Tanggal" :value="$d('doc_date')" required :readonly="$locked" :error="$e('doc_date')" />
            <x-dl2::field.select name="type" label="Jenis" :options="FacHandover::TYPES" :value="$v('type')" required :readonly="$locked" :error="$e('type')" />
        </x-dl2::form.row>
        <x-dl2::form.row>
            <x-dl2::field.select name="asset_id" label="Aset" :options="['' => '(pilih aset)'] + $assets" :value="$v('asset_id')" required :readonly="$locked" :error="$e('asset_id')" hint="Posisi dan pemegang sekarang tampil di dalam kurung." />
        </x-dl2::form.row>
        <x-dl2::form.row :cols="3">
            <x-dl2::field.select name="employee_id" label="Karyawan" :options="['' => '(tidak ada)'] + $employees" :value="$v('employee_id')" :readonly="$locked" :error="$e('employee_id')" hint="Penerima (pinjam / bawa pulang) atau yang mengembalikan." />
            <x-dl2::field.select name="department_id" label="Departemen" :options="['' => '(tidak ada)'] + $departments" :value="$v('department_id')" :readonly="$locked" :error="$e('department_id')" hint="Untuk aset yang dipakai bersama satu departemen." />
            <x-dl2::field.date name="expected_return" label="Perkiraan kembali" :value="$d('expected_return')" :readonly="$locked" :error="$e('expected_return')" hint="Wajib untuk pinjam dan bawa pulang." />
        </x-dl2::form.row>
        <x-dl2::form.row :cols="2">
            <x-dl2::field.select name="condition_out" label="Kondisi saat keluar / kembali" :options="$conditions" :value="$v('condition_out')" :readonly="$locked" :error="$e('condition_out')" />
            <x-dl2::field.text name="damage_note" label="Catatan selisih / kerusakan" :value="$v('damage_note')" maxlength="255" :readonly="$locked" :error="$e('damage_note')" />
        </x-dl2::form.row>
        <x-dl2::form.row :cols="2">
            <x-dl2::field.text name="purpose" label="Tujuan / kronologi" :value="$v('purpose')" maxlength="255" :readonly="$locked" :error="$e('purpose')" hint="Wajib untuk laporan hilang." />
            <x-dl2::field.upload name="photo" label="Foto lampiran" accept=".jpg,.jpeg,.png,image/jpeg,image/png" :preview="true" :value="$h->photo_path ? ['url' => route('fac-handovers.file', $h), 'name' => $h->photo_name] : null" :readonly="$locked" :error="$e('photo')" />
        </x-dl2::form.row>
        <p class="ak-note">Setelah disetujui Supervisor: pinjam / bawa pulang menetapkan pemegang dan tanggal wajib kembali; pengembalian memindahkan aset kembali ke kantor (kondisi Rusak = Dalam perbaikan); laporan hilang menandai aset Hilang.</p>
    </x-dl2::form>
@endsection
