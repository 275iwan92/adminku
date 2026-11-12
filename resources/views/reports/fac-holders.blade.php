{{-- REMARK
 Tujuan       : Laporan Aset per Pemegang (modul FAP): satu orang atau departemen memegang aset apa saja (pinjam / bawa pulang), sejak kapan, wajib kembali kapan, dan statusnya (Terlambat berwarna); pemegang yang sudah keluar ditandai.
 Sumber hulu  : FacReportController@holders.
 Input        : rows, late.
 Output       : x-dl2::report dikelompokkan per pemegang (cetak / ekspor).
 Dipakai oleh : route reports.fac-holders (dashboard Perawatan Gedung dan Aset > Per Pemegang).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Aset per Pemegang'])
@section('body')
    <x-dl2::action-bar align="start">
        <x-dl2::button class="sstock-btn-slate" variant="secondary" icon="fa-list" label="Semua yang di luar kantor" :href="route('reports.fac-holders')" />
        <x-dl2::button class="sstock-btn-coral" variant="secondary" icon="fa-clock" label="Hanya yang terlambat" :href="route('reports.fac-holders', ['late' => 1])" />
    </x-dl2::action-bar>
    <x-dl2::report phone-list title="Aset per Pemegang" subtitle="Aset yang sedang dipinjam atau dibawa pulang, per pemegang" :company="\App\Support\Company::report()" :printed-by="raw(auth()->user()->name)" paper="a4l"
        :criteria="['Tanggal' => raw(Devlib2\Locale::d(now())), 'Tampilan' => raw($late ? 'Hanya yang terlambat' : 'Semua yang di luar kantor')]"
        group-by="holder" :groups="['holder' => 'Per Pemegang', 'category' => 'Per Kategori']"
        :summary="[['label' => 'Jumlah aset di luar kantor', 'value' => count($rows)], ['label' => 'Terlambat kembali', 'value' => collect($rows)->filter(fn ($r) => str_starts_with($r['status'], 'Terlambat'))->count()]]"
        :columns="[
            ['key' => 'holder', 'label' => 'Pemegang'],
            ['key' => 'left', 'label' => 'Catatan'],
            ['key' => 'code', 'label' => 'Kode'],
            ['key' => 'asset', 'label' => 'Aset'],
            ['key' => 'category', 'label' => 'Kategori'],
            ['key' => 'position', 'label' => 'Posisi'],
            ['key' => 'since', 'label' => 'Sejak'],
            ['key' => 'due', 'label' => 'Wajib kembali'],
            ['key' => 'status', 'label' => 'Status'],
        ]"
        :rows="$rows" :signatures="[['Dibuat oleh', 'Admin'], ['Diperiksa oleh', 'Supervisor']]" source="m_fac_asset" />
@endsection
