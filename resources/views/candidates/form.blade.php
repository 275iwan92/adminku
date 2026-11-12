{{-- REMARK
 Tujuan       : Pendaftaran dan kartu Kandidat rekrutmen (Single Form kind=master): tab Data (NIK KTP wajib, nama, bagian dilamar boleh banyak, kontak, foto KTP dan CV; kandidat lama juga tahap dan status) dan tab Proses (riwayat tahap: jadwal, jam, penilai, hasil, catatan).
                Peringatan bila NIK pernah melamar atau sudah jadi karyawan; kandidat yang sudah di tahap akhir mendapat tombol Jadikan karyawan.
 Sumber hulu  : CandidateController (candidate, departments, chosen, steps, events, warnings, isEnd).
 Input        : candidate, departments, chosen, steps, events, warnings, isEnd.
 Output       : POST candidates.store (baru) / PUT candidates.update (ubah).
 Dipakai oleh : routes candidates.create, candidates.show.
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Kandidat'])
@section('body')
    @php
        $isNew = ! $candidate->exists;
        $v = fn (string $k) => old($k, $candidate->{$k});
        $d = fn (string $k) => old($k, $candidate->{$k}?->format('Y-m-d'));
        $e = fn (string $k) => $errors->first($k) ?: null;
    @endphp
    @include('partials.flash')
    @foreach ($warnings as $w)
        <x-dl2::alert tone="warning" :message="raw($w)" />
    @endforeach
    <x-dl2::form :title="$isNew ? 'Kandidat · Baru' : 'Kandidat · '.raw($candidate->name)" :subtitle="$isNew ? null : raw($candidate->nik)" panel="top" kind="master" enctype="multipart/form-data"
        :tabs="$isNew ? ['data' => 'Data'] : ['data' => 'Data', 'proses' => 'Proses']"
        :status="$isNew ? 'new' : ($candidate->status === 'active' ? 'active' : 'inactive')" :back="route('candidates.index')" :new-url="route('candidates.create')" :hide="['deactivate', 'activate']"
        :action="$isNew ? route('candidates.store') : route('candidates.update', $candidate)" :method="$isNew ? 'post' : 'put'"
        :created-at="$candidate->created_at?->format('Y-m-d H:i:s')" :updated-at="$candidate->updated_at?->format('Y-m-d H:i:s')">
        <x-dl2::form-tab key="data">
            <x-dl2::form.row :cols="3">
                <x-dl2::field.text name="nik" label="NIK KTP" :value="$v('nik')" code :case-toggle="false" required maxlength="16" :error="$e('nik')" hint="Wajib, 16 angka. Kunci pencarian kandidat." />
                <x-dl2::field.text name="name" label="Nama" :value="$v('name')" required maxlength="150" :error="$e('name')" />
                <x-dl2::field.date name="applied_at" label="Tanggal Lamar" :value="$d('applied_at')" required :error="$e('applied_at')" />
            </x-dl2::form.row>
            <x-dl2::form.row :cols="2">
                <x-dl2::field.multi-select name="departments" label="Bagian Dilamar" :options="$departments" :value="$chosen" empty-text="Pilih bagian (boleh lebih dari satu)" noun="bagian" :error="$e('departments')" hint="Wajib, minimal satu. Boleh melamar ke beberapa bagian." />
                <x-dl2::field.text name="position" label="Posisi Diminati" :value="$v('position')" maxlength="100" :error="$e('position')" />
            </x-dl2::form.row>
            <x-dl2::form.row :cols="4">
                <x-dl2::field.phone name="phone" label="HP" :value="$v('phone')" :error="$e('phone')" />
                <x-dl2::field.text name="email" label="E-mail" :value="$v('email')" maxlength="150" :error="$e('email')" />
                <x-dl2::field.date name="birth_date" label="Tanggal Lahir" :value="$d('birth_date')" :error="$e('birth_date')" />
                <x-dl2::field.select name="education" label="Pendidikan Terakhir" :options="\App\Models\Candidate::EDUCATIONS" :value="$v('education')" :error="$e('education')" />
            </x-dl2::form.row>
            <x-dl2::form.row :cols="3">
                <x-dl2::field.select name="source" label="Sumber Lamaran" :options="\App\Models\Candidate::SOURCES" :value="$v('source')" :error="$e('source')" />
                @unless ($isNew)
                    <x-dl2::field.select name="step_id" label="Tahap Sekarang" :options="$steps" :value="$v('step_id')" required :error="$e('step_id')" hint="Boleh diubah ke tahap mana pun. Memindah dari papan menambah riwayat otomatis." />
                    <x-dl2::field.select name="status" label="Status" :options="\App\Models\Candidate::STATUSES" :value="$v('status')" required :error="$e('status')" hint="Ditolak atau Mundur menyembunyikan kartu dari papan." />
                @endunless
            </x-dl2::form.row>
            <div class="rk-photos">
                <div class="rk-photo-cell">
                    <label class="rk-photo-label">Foto Profil</label>
                    <x-dl2::photo-box name="photo" :photo="$candidate->photo_path ? route('candidates.file', [$candidate, 'photo']) : null" :label="$candidate->name ?: 'Foto'" size="lg" caption="Klik untuk menambah foto" />
                    @if ($candidate->photo_path)<a href="#" class="rk-zoom" data-lightbox="{{ route('candidates.file', [$candidate, 'photo']) }}" data-name="{{ $candidate->photo_name }}"><i class="fa-solid fa-magnifying-glass-plus"></i> Perbesar</a>@endif
                    <small class="rk-photo-hint">Klik kotak untuk memilih, menggeser, dan memperbesar; hasil dipotong 3:4. Tampil sebagai avatar di kartu papan.</small>
                    @if ($e('photo'))<span class="rk-photo-err">{{ $e('photo') }}</span>@endif
                </div>
                <div class="rk-photo-cell rk-photo-cell--ktp">
                    <label class="rk-photo-label">Foto KTP</label>
                    @php $ktpPdf = $candidate->ktp_name && str_ends_with(strtolower($candidate->ktp_name), '.pdf'); @endphp
                    <x-dl2::photo-box name="ktp_crop" :photo="$candidate->ktp_path && ! $ktpPdf ? route('candidates.file', [$candidate, 'ktp']) : null" label="KTP" :out-w="856" :out-h="540" size="lg" style="--pb-w:280px" caption="Klik untuk menambah foto KTP" />
                    @if ($candidate->ktp_path)
                        @if ($ktpPdf)<a href="{{ route('candidates.file', [$candidate, 'ktp']) }}" target="_blank" rel="noopener" class="rk-zoom"><i class="fa-regular fa-file-pdf"></i> Buka PDF ({{ $candidate->ktp_name }})</a>
                        @else<a href="#" class="rk-zoom" data-lightbox="{{ route('candidates.file', [$candidate, 'ktp']) }}" data-name="{{ $candidate->ktp_name }}"><i class="fa-solid fa-magnifying-glass-plus"></i> Perbesar</a>@endif
                    @endif
                    <small class="rk-photo-hint">Dipotong sebesar kartu (856 x 540 px). Di HP, pilih berkas bisa langsung memakai kamera. Punya PDF? Unggah apa adanya di bawah.</small>
                    <x-dl2::field.upload name="ktp" label="Atau unggah PDF / gambar apa adanya" accept="application/pdf,image/jpeg,image/png,image/webp" :error="$e('ktp')" hint="JPG, PNG, WEBP, atau PDF, paling besar 5 MB." />
                    @if ($e('ktp_crop'))<span class="rk-photo-err">{{ $e('ktp_crop') }}</span>@endif
                </div>
            </div>
            <x-dl2::form.row :cols="2">
                <x-dl2::field.upload name="cv" label="CV" accept="image/*,application/pdf,.doc,.docx" :value="$candidate->cv_path ? ['url' => route('candidates.file', [$candidate, 'cv']), 'name' => $candidate->cv_name] : null" :error="$e('cv')" hint="Opsional. PDF, gambar, atau Word, paling besar 5 MB." />
            </x-dl2::form.row>
            <x-dl2::form.row>
                <x-dl2::field.text name="note" label="Catatan" :value="$v('note')" maxlength="255" :error="$e('note')" />
            </x-dl2::form.row>
            @if ($isEnd)
                <x-dl2::form.row>
                    <x-dl2::button class="sstock-btn-teal" variant="primary" icon="fa-id-card" label="Jadikan karyawan" :href="route('employees.create', ['kandidat' => $candidate->id])" />
                </x-dl2::form.row>
            @endif
        </x-dl2::form-tab>
        @unless ($isNew)
            <x-dl2::form-tab key="proses">
                <x-dl2::lines name="events" title="Riwayat Tahap" add-label="+ Tambah Baris" :rows="$events" :min-rows="0"
                    :columns="[
                        ['key' => 'step_id', 'label' => 'Tahap', 'type' => 'select', 'options' => $steps, 'required' => true, 'width' => '190px'],
                        ['key' => 'scheduled_on', 'label' => 'Tanggal', 'type' => 'date', 'width' => '160px'],
                        ['key' => 'scheduled_time', 'label' => 'Jam', 'type' => 'text', 'width' => '90px', 'placeholder' => '09:30'],
                        ['key' => 'assessor', 'label' => 'Penilai', 'type' => 'select', 'options' => $assessors, 'width' => '190px'],
                        ['key' => 'result', 'label' => 'Hasil', 'type' => 'select', 'options' => \App\Models\Candidate::RESULTS, 'required' => true, 'width' => '150px'],
                        ['key' => 'note', 'label' => 'Catatan', 'type' => 'text'],
                    ]" />
                <p class="ak-note">Satu baris per tahap yang dijalani. Pindah tahap di papan menambah baris Menunggu; isi tanggal, jam, penilai, lalu ubah Hasil menjadi Lulus atau Tidak lulus setelah selesai. Jadwal Menunggu yang terdekat tampil di papan dan di Daftar.</p>
                <h4 style="margin:14px 0 6px">Riwayat Pindah</h4>
                <table class="dl2-table" style="width:100%">
                    <thead><tr><th>Waktu</th><th>Oleh</th><th>Dari</th><th>Ke</th><th>Jadwal</th></tr></thead>
                    <tbody>
                    @forelse ($moves as $m)
                        <tr><td>{{ $m->moved_at->format('d-m-Y H:i:s') }}</td><td>{{ $m->mover?->name ?? '-' }}</td><td>{{ $m->from?->name ?? '-' }}@if ($m->no_result) <em>(dipindah tanpa hasil)</em>@endif</td><td>{{ $m->to->name }}</td><td>{{ $m->scheduled_on ? $m->scheduled_on->format('d-m-Y').' '.$m->scheduled_time.($m->assessor ? ' · '.$m->assessor : '') : '' }}</td></tr>
                    @empty
                        <tr><td colspan="5">Belum ada perpindahan di papan.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </x-dl2::form-tab>
        @endunless
    </x-dl2::form>
    @include('partials.lightbox')
@endsection
