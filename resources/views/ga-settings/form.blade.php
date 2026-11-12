{{-- REMARK
 Tujuan       : Layar Pengaturan GA (modul GAP; Single Form kind=master, satu data): bagian Kendaraan (ambang Segera dalam hari dan km) dan bagian Perawatan AC (interval cuci rutin dan servis besar, hari dan jam kunjungan,
                kapasitas per hari per vendor, ambang Segera, jam pesan siap kirim untuk PIC). Isian terisi nilai yang sedang berlaku (atau bawaan bila belum pernah diatur).
 Sumber hulu  : GaSettingsController@edit (s = nilai berlaku, dayNames).
 Input        : s, dayNames.
 Output       : PUT ga-settings.update.
 Dipakai oleh : route ga-settings.edit (menu GA > Pengaturan GA, atau ubin Pengaturan di hub Setup Kendaraan / AC dengan ?dari=fleet|ac supaya Kembali ke hub itu).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Pengaturan GA'])
@section('body')
    @php
        $v = fn (string $k) => old($k, $s[$k]);
        $e = fn (string $k) => $errors->first($k) ?: null;
        $days = old('ac_days', $s['ac_days']);
    @endphp
    @include('partials.flash')
    <x-dl2::form title="Pengaturan GA" subtitle="Aturan kerja Perawatan Kendaraan dan Perawatan AC. Berlaku segera; jadwal yang sudah dibuat tidak berubah." panel="top" kind="master" status="active"
        :hide="['activate', 'deactivate', 'new']" :back="$back" :action="route('ga-settings.update', $keep)" method="put">
        <x-dl2::divider label="Kendaraan" />
        <x-dl2::form.row :cols="3">
            <x-dl2::field.number name="vehicle_soon_days" label="Segera Jatuh Tempo (hari)" :min="1" :max="365" :value="$v('vehicle_soon_days')" required :error="$e('vehicle_soon_days')" hint="Dokumen kendaraan berstatus Segera bila jatuh tempo dalam sekian hari (1-365). Bawaan 30." />
            <x-dl2::field.decimal name="vehicle_soon_km" label="Segera Servis (km)" :decimals="0" unit="km" :value="$v('vehicle_soon_km')" required :error="$e('vehicle_soon_km')" hint="Servis berstatus Segera bila sisa kilometernya tinggal sekian (100-50.000). Bawaan 1.000." />
        </x-dl2::form.row>

        <x-dl2::divider label="Perawatan AC" />
        <x-dl2::form.row :cols="3">
            <x-dl2::field.number name="ac_wash_months" label="Cuci Rutin Tiap (bulan)" :min="1" :max="60" :value="$v('ac_wash_months')" required :error="$e('ac_wash_months')" hint="Jarak antar cuci rutin per AC (1-60). Bawaan 3." />
            <x-dl2::field.number name="ac_major_months" label="Servis Besar Tiap (bulan)" :min="1" :max="60" :value="$v('ac_major_months')" required :error="$e('ac_major_months')" hint="Jarak antar servis besar (1-60), tidak boleh lebih pendek dari cuci rutin. Bawaan 12." />
            <x-dl2::field.number name="ac_soon_days" label="Segera Jatuh Tempo (hari)" :min="1" :max="365" :value="$v('ac_soon_days')" required :error="$e('ac_soon_days')" hint="AC berstatus Segera bila jatuh tempo dalam sekian hari (1-365). Bawaan 30." />
        </x-dl2::form.row>
        <x-dl2::form.row>
            <x-dl2::field.checkboxes name="ac_days" label="Hari Kunjungan" :options="$dayNames" :value="$days" required :error="$e('ac_days') ?: $e('ac_days.0')" hint="Vendor hanya boleh dijadwalkan pada hari yang dicentang (minimal satu). Bawaan Senin sampai Jumat." />
        </x-dl2::form.row>
        <x-dl2::form.row :cols="3">
            <x-dl2::field.time name="ac_start_time" label="Jam Mulai Kunjungan" :value="$v('ac_start_time')" required :error="$e('ac_start_time')" hint="Format JJ:MM. Bawaan 09:00." />
            <x-dl2::field.time name="ac_end_time" label="Jam Akhir Kunjungan" :value="$v('ac_end_time')" required :error="$e('ac_end_time')" hint="Setelah jam mulai. Bawaan 16:00." />
            <x-dl2::field.number name="ac_capacity" label="Kapasitas AC per Hari" :min="1" :max="20" :value="$v('ac_capacity')" required :error="$e('ac_capacity')" hint="Jumlah AC yang boleh dikerjakan satu vendor dalam sehari (1-20). Bawaan 3." />
        </x-dl2::form.row>
        <x-dl2::form.row :cols="3">
            <x-dl2::field.time name="ac_pic_notice_time" label="Jam Pesan PIC Siap Kirim" :value="$v('ac_pic_notice_time')" required :error="$e('ac_pic_notice_time')" hint="Salinan pemberitahuan untuk PIC ruangan siap dikirim sehari sebelum kunjungan pada jam ini. Bawaan 15:00." />
        </x-dl2::form.row>
    </x-dl2::form>
@endsection
