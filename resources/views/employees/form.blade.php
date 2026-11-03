{{-- REMARK
 Tujuan       : Form Karyawan (Single Form kind=master, lima tab): Profil (data pribadi, KTP, alamat, kontak darurat), Pekerjaan (bagian, jabatan, status kerja, akun Jays, keluar), Dokumen, Keluarga, Kontrak.
                Kode tetap setelah disimpan. Nonaktifkan memakai alasan + tanggal efektif dari tab Pekerjaan; berkas pindaian dokumen belum ada (hanya nomor dan masa berlaku).
 Sumber hulu  : EmployeeController (employee, departments, users, family, docs, contracts).
 Input        : employee, departments, users, family, docs, contracts.
 Output       : POST employees.store (baru) / PUT employees.update (ubah, Aktifkan, Nonaktifkan).
 Dipakai oleh : routes employees.create, employees.show.
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Karyawan'])
@section('body')
    @php
        $isNew = ! $employee->exists;
        $v = fn (string $k) => old($k, $employee->{$k});
        $d = fn (string $k) => old($k, $employee->{$k}?->format('Y-m-d'));
        $e = fn (string $k) => $errors->first($k) ?: null;
        $exited = ! $isNew && ! $employee->is_active;
        $family = array_map(fn ($r) => $r + ['is_deceased' => false], $family);
    @endphp
    @include('partials.flash')
    <x-dl::form :title="$isNew ? 'Karyawan · Baru' : 'Karyawan · '.raw($employee->code)" :subtitle="$isNew ? null : raw($employee->name)" panel="top" kind="master" enctype="multipart/form-data"
        :tabs="['profile' => 'Profil', 'job' => 'Pekerjaan', 'docs' => 'Dokumen', 'family' => 'Keluarga', 'contracts' => 'Kontrak'] + ($isNew ? [] : ['discipline' => 'Disiplin'])"
        :status="$isNew ? 'new' : ($employee->is_active ? 'active' : 'inactive')" :back="route('employees.index')" :new-url="route('employees.create')"
        :action="$isNew ? route('employees.store') : route('employees.update', $employee)" :hidden="$isNew ? [] : ['_method' => 'PUT']"
        :created-at="$employee->created_at?->format('Y-m-d H:i:s')" :updated-at="$employee->updated_at?->format('Y-m-d H:i:s')">
        <x-slot:profile>
            <div class="rk-photos">
                <div class="rk-photo-cell">
                    <label class="rk-photo-label">Foto Profil</label>
                    <x-dl::photo-box name="photo" :photo="$employee->photo_path ? route('employees.file', [$employee, 'photo']) : null" :label="$employee->name ?: 'Foto'" size="lg" caption="Klik untuk menambah foto" />
                    @if ($employee->photo_path)<a href="#" class="rk-zoom" data-lightbox="{{ route('employees.file', [$employee, 'photo']) }}" data-name="{{ $employee->photo_name }}"><i class="fa-solid fa-magnifying-glass-plus"></i> Perbesar</a>@endif
                    <small class="rk-photo-hint">Klik kotak untuk memilih, menggeser, dan memperbesar; hasil dipotong 3:4 (600 x 800 px).</small>
                    @if ($e('photo'))<span class="rk-photo-err">{{ $e('photo') }}</span>@endif
                </div>
                <div class="rk-photo-cell rk-photo-cell--ktp">
                    <label class="rk-photo-label">Scan KTP</label>
                    @php $ktpPdf = $employee->ktp_name && str_ends_with(strtolower($employee->ktp_name), '.pdf'); @endphp
                    <x-dl::photo-box name="ktp_crop" :photo="$employee->ktp_path && ! $ktpPdf ? route('employees.file', [$employee, 'ktp']) : null" label="KTP" :out-w="856" :out-h="540" size="lg" style="--pb-w:280px" caption="Klik untuk menambah scan KTP" />
                    @if ($employee->ktp_path)
                        @if ($ktpPdf)<a href="{{ route('employees.file', [$employee, 'ktp']) }}" target="_blank" rel="noopener" class="rk-zoom"><i class="fa-regular fa-file-pdf"></i> Buka PDF ({{ $employee->ktp_name }})</a>
                        @else<a href="#" class="rk-zoom" data-lightbox="{{ route('employees.file', [$employee, 'ktp']) }}" data-name="{{ $employee->ktp_name }}"><i class="fa-solid fa-magnifying-glass-plus"></i> Perbesar</a>@endif
                    @endif
                    <small class="rk-photo-hint">Dipotong sebesar kartu (856 x 540 px). Punya PDF? Unggah apa adanya di bawah.</small>
                    <x-dl::field.upload name="ktp_file" label="Atau unggah PDF / gambar apa adanya" accept="application/pdf,image/jpeg,image/png,image/webp" :error="$e('ktp_file')" hint="JPG, PNG, WEBP, atau PDF, paling besar 5 MB. Data pribadi: hanya terlihat oleh pemegang hak Karyawan." />
                    @if ($e('ktp_crop'))<span class="rk-photo-err">{{ $e('ktp_crop') }}</span>@endif
                </div>
            </div>
            <x-dl::form.row :cols="3">
                <x-dl::field.text name="code" label="Kode Karyawan" :value="$v('code')" code :case-toggle="false" required :readonly="! $isNew" maxlength="20" :error="$e('code')" hint="Unik. Tidak bisa diubah setelah disimpan." />
                <x-dl::field.text name="name" label="Nama Lengkap" :value="$v('name')" required maxlength="150" :error="$e('name')" />
                <x-dl::field.text name="nickname" label="Panggilan" :value="$v('nickname')" maxlength="50" :error="$e('nickname')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="4">
                <x-dl::field.select name="gender" label="Jenis Kelamin" :options="\App\Models\Employee::GENDERS" :value="$v('gender')" required :error="$e('gender')" />
                <x-dl::field.text name="birth_place" label="Tempat Lahir" :value="$v('birth_place')" maxlength="100" :error="$e('birth_place')" />
                <x-dl::field.date name="birth_date" label="Tanggal Lahir" :value="$d('birth_date')" :error="$e('birth_date')" />
                <x-dl::field.select name="marital" label="Status Kawin" :options="\App\Models\Employee::MARITALS" :value="$v('marital')" :error="$e('marital')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="4">
                <x-dl::field.text name="nik_ktp" label="NIK KTP" :value="$v('nik_ktp')" maxlength="16" :error="$e('nik_ktp')" hint="16 angka. Data pribadi: hanya tampil di layar ini." />
                <x-dl::field.text name="npwp" label="NPWP" :value="$v('npwp')" maxlength="30" :error="$e('npwp')" />
                <x-dl::field.text name="religion" label="Agama" :value="$v('religion')" maxlength="20" :error="$e('religion')" />
                <x-dl::field.phone name="phone" label="HP" :value="$v('phone')" :error="$e('phone')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="3">
                <x-dl::field.text name="email" label="E-mail Pribadi" :value="$v('email')" maxlength="150" :error="$e('email')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="2">
                <x-dl::field.lookup name="ktp_area" lookup="geo_area" label="Wilayah KTP" placeholder="Cari kecamatan / kota / kode pos..." :value="old('ktp_area', $employee->areaKey('ktp'))" :error="$e('ktp_area')" />
                <x-dl::field.text name="ktp_address" label="Alamat KTP (jalan, RT/RW)" :value="$v('ktp_address')" maxlength="255" :error="$e('ktp_address')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="2">
                <x-dl::field.lookup name="home_area" lookup="geo_area" label="Wilayah Domisili" placeholder="Kosongkan bila sama dengan KTP" :value="old('home_area', $employee->areaKey('home'))" :error="$e('home_area')" />
                <x-dl::field.text name="home_address" label="Alamat Domisili" :value="$v('home_address')" maxlength="255" :error="$e('home_address')" hint="Kosong = sama dengan KTP." />
            </x-dl::form.row>
            <x-dl::form.row :cols="3">
                <x-dl::field.text name="emergency_name" label="Kontak Darurat" :value="$v('emergency_name')" maxlength="150" :error="$e('emergency_name')" />
                <x-dl::field.text name="emergency_relation" label="Hubungan" :value="$v('emergency_relation')" maxlength="50" :error="$e('emergency_relation')" />
                <x-dl::field.phone name="emergency_phone" label="HP Kontak Darurat" :value="$v('emergency_phone')" :error="$e('emergency_phone')" />
            </x-dl::form.row>
        </x-slot:profile>
        <x-slot:job>
            <x-dl::form.row :cols="3">
                <x-dl::field.select name="department_id" label="Bagian" :options="$departments" :value="$v('department_id')" required :error="$e('department_id')" hint="Bagian = Departemen di Data Master." />
                <x-dl::field.text name="position" label="Jabatan" :value="$v('position')" maxlength="100" :error="$e('position')" />
                <x-dl::field.select name="employment" label="Status Kerja" :options="\App\Models\Employee::EMPLOYMENTS" :value="$v('employment')" required :error="$e('employment')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="3">
                <x-dl::field.date name="join_date" label="Tanggal Masuk" :value="$d('join_date')" required :error="$e('join_date')" />
                <x-dl::field.select name="work_status" label="Status" :options="\App\Models\Employee::WORK_STATUSES" :value="$v('work_status')" required :error="$e('work_status')" />
                <x-dl::field.select name="user_id" label="Akun AdminKU" :options="$users" :value="$v('user_id')" :error="$e('user_id')" hint="Opsional. Akun ikut nonaktif saat karyawan dinonaktifkan." />
            </x-dl::form.row>
            @if ($exited)
                <x-dl::form.row :cols="3">
                    <x-dl::field.display label="Alasan Keluar" :value="raw(\App\Models\Employee::EXIT_REASONS[$employee->exit_reason] ?? '-')" />
                    <x-dl::field.display label="Tanggal Efektif Keluar" :value="raw($employee->exit_date?->format('d-m-Y') ?? '-')" />
                    <x-dl::field.display label="Catatan Keluar" :value="raw($employee->exit_note ?: '-')" />
                </x-dl::form.row>
            @elseif (! $isNew)
                <x-dl::form.row :cols="3">
                    <x-dl::field.select name="exit_reason" label="Alasan Keluar" :options="\App\Models\Employee::EXIT_REASONS" :value="$v('exit_reason')" :error="$e('exit_reason')" hint="Diisi bersama tanggal lalu tekan Deactivate. Karyawan tidak dihapus." />
                    <x-dl::field.date name="exit_date" label="Tanggal Efektif Keluar" :value="$d('exit_date')" :error="$e('exit_date')" />
                    <x-dl::field.text name="exit_note" label="Catatan Keluar" :value="$v('exit_note')" maxlength="255" :error="$e('exit_note')" />
                </x-dl::form.row>
            @endif
        </x-slot:job>
        <x-slot:docs>
            <x-dl::lines name="docs" title="Dokumen" add-label="+ Tambah Dokumen" :rows="$docs" :min-rows="0"
                :columns="[
                    ['key' => 'doc_type', 'label' => 'Jenis', 'type' => 'select', 'options' => \App\Models\Employee::DOC_TYPES, 'required' => true, 'width' => '220px'],
                    ['key' => 'doc_no', 'label' => 'Nomor', 'type' => 'text'],
                    ['key' => 'expiry_date', 'label' => 'Berlaku s/d', 'type' => 'date', 'width' => '170px'],
                    ['key' => 'note', 'label' => 'Catatan', 'type' => 'text'],
                ]" />
            <p class="dl-note">Isi Berlaku s/d untuk dokumen yang habis masa berlakunya (mis. SIM); 30 hari sebelumnya karyawan muncul di kolom Perhatian. Berkas pindaian belum disimpan di sini.</p>
        </x-slot:docs>
        <x-slot:family>
            <x-dl::lines name="family" title="Keluarga" add-label="+ Tambah Anggota" :rows="$family" :min-rows="0"
                :columns="[
                    ['key' => 'relation', 'label' => 'Hubungan', 'type' => 'select', 'options' => \App\Models\Employee::RELATIONS, 'required' => true, 'width' => '170px'],
                    ['key' => 'name', 'label' => 'Nama', 'type' => 'text', 'required' => true],
                    ['key' => 'birth_date', 'label' => 'Tanggal lahir', 'type' => 'date', 'width' => '170px'],
                    ['key' => 'nik', 'label' => 'NIK', 'type' => 'text', 'width' => '190px'],
                    ['key' => 'is_deceased', 'label' => 'Meninggal', 'type' => 'bool', 'width' => '120px'],
                ]" />
        </x-slot:family>
        <x-slot:contracts>
            <x-dl::lines name="contracts" title="Kontrak" add-label="+ Tambah Kontrak" :rows="$contracts" :min-rows="0"
                :columns="[
                    ['key' => 'contract_no', 'label' => 'No. Kontrak', 'type' => 'text'],
                    ['key' => 'employment', 'label' => 'Jenis', 'type' => 'select', 'options' => \App\Models\Employee::EMPLOYMENTS, 'required' => true, 'width' => '150px'],
                    ['key' => 'start_date', 'label' => 'Mulai', 'type' => 'date', 'required' => true, 'width' => '160px'],
                    ['key' => 'end_date', 'label' => 'Selesai', 'type' => 'date', 'width' => '160px'],
                    ['key' => 'probation_end', 'label' => 'Percobaan s/d', 'type' => 'date', 'width' => '160px'],
                ]" />
            <p class="dl-note">Kontrak terbaru (mulai paling akhir) yang dipantau: 60 hari sebelum Selesai, karyawan muncul di kolom Perhatian. Kosongkan Selesai untuk karyawan tetap. Tanpa gaji.</p>
        </x-slot:contracts>
        @unless ($isNew)
            <x-slot:discipline>
                @php $dall = $disciplines; @endphp
                <table class="dl-table" style="width:100%">
                    <thead><tr><th>No. Surat</th><th>Tanggal</th><th>Jenis</th><th>Berlaku s/d</th><th>Status</th><th>Alasan</th></tr></thead>
                    <tbody>
                    @forelse ($dall as $d)
                        <tr><td><a href="{{ route('disciplines.show', $d) }}">{{ $d->doc_no }}</a></td><td>{{ $d->issued_on->format('d-m-Y') }}</td><td>{{ \App\Models\Discipline::TYPES[$d->type] }}</td><td>{{ $d->until_on?->format('d-m-Y') ?? '-' }}</td><td>{{ $d->stateLabel($dall) }}</td><td>{{ $d->reason }}</td></tr>
                    @empty
                        <tr><td colspan="6">Belum ada SP atau PHK.</td></tr>
                    @endforelse
                    </tbody>
                </table>
                @if (auth()->user()->canWrite('HRD') && $employee->is_active)
                    <p style="margin-top:10px"><x-dl::button class="sstock-btn-amber" variant="secondary" icon="fa-gavel" label="Terbitkan SP" :href="route('disciplines.create', ['employee' => $employee->id])" /></p>
                @endif
            </x-slot:discipline>
        @endunless
    </x-dl::form>
    @include('partials.lightbox')
@endsection
