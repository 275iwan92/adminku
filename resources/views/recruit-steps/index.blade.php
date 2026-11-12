{{-- REMARK
 Tujuan       : Setup Rekrutmen (Grid Master, modul HRR): daftar tahap proses berurutan; Daftar dan Diterima tetap di awal dan akhir, tahap tengah bebas.
 Sumber hulu  : RecruitStepController@index.
 Input        : rows.
 Output       : tombol Kembali ke papan Rekrutmen (candidates.index) + x-dl2::grid; klik 2x / Edit membuka form tahap.
 Dipakai oleh : route recruit-steps.index (tombol Setup di Rekrutmen).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Setup Rekrutmen'])
@section('body')
    @include('partials.flash')
    <x-dl2::action-bar align="start">
        <x-dl2::button class="sstock-btn-slate" variant="secondary" icon="fa-arrow-left" label="Kembali" :href="route('candidates.index')" />
    </x-dl2::action-bar>
    <x-dl2::grid id="recruit-steps" :rows="$rows" actions="edit" :new-url="route('recruit-steps.create')" :open-url="url('recruit-steps/{id}')"
        search="Cari tahap..."
        :phone-cards="['title' => 'name', 'sub' => 'kind', 'meta' => ['sort', 'candidates']]"
        :columns="[
            ['key' => 'sort', 'label' => 'Urutan', 'type' => 'integer'],
            ['key' => 'name', 'label' => 'Tahap'],
            ['key' => 'kind', 'label' => 'Jenis'],
            ['key' => 'scheduled', 'label' => 'Berjadwal'],
            ['key' => 'candidates', 'label' => 'Kandidat di tahap ini', 'type' => 'integer'],
        ]"
        :stats="[['label' => 'Semua tahap'], ['label' => 'Nonaktif', 'where' => ['active' => false], 'tone' => 'warn']]" />
    <p class="ak-note">Daftar selalu pertama dan Diterima selalu terakhir. Tahap di antaranya bebas ditambah, diganti nama, dan diurutkan lewat angka Urutan. Tahap tidak dihapus, hanya dinonaktifkan.</p>
@endsection
