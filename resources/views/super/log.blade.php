{{-- REMARK
 Tujuan       : Log Aktivitas (super admin): satu baris per aktivitas = Waktu, User, IP, Lokasi (perkiraan), Aksi, Rincian (nama rute + alamat). Pilih bulan, cari, urutkan, unduh CSV.
 Sumber hulu  : SuperAdminController@log (ActivityLog::read, 1500 baris terbaru bulan itu; kunci loc = IpLocation::label, perkiraan lokasi + ISP dari IP, hanya tampilan).
 Input        : rows, month, months.
 Output       : x-dl2::grid tanpa aksi baris; tautan unduh CSV bulan itu.
 Dipakai oleh : route super.log (menu Keamanan > Log Aktivitas, hanya super admin).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Log Aktivitas'])
@section('body')
    @include('partials.flash')
    <div>
    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin: 4px 0 10px">
        <h2 class="sstock-pagetitle" style="margin: 0 12px 0 0">Log Aktivitas</h2>
        <form method="GET" action="{{ route('super.log') }}" style="display: flex; gap: 8px; align-items: center">
            <label for="month">Bulan</label>
            <select id="month" name="month" onchange="this.form.submit()">
                @foreach ($months as $m)
                    <option value="{{ $m }}" @selected($m === $month)>{{ $m }}</option>
                @endforeach
            </select>
        </form>
        <x-dl2::button variant="secondary" size="sm" icon="fa-download" :href="route('super.log.download', ['month' => $month])" label="Unduh CSV" />
        <span class="ak-note" style="margin: 0">{{ count($rows) }} baris terbaru (maks. 1.500). Isi formulir dan kata sandi tidak dicatat. Lokasi hanya perkiraan kasar (IP seluler sering menunjuk kota pusat jaringan operator, bukan posisi orangnya); IP publik dikirim ke layanan ipwho.is untuk dicari, IP lokal tidak.</span>
    </div>
    @php $tones = ['Buka' => 'neutral', 'Input' => 'success', 'Ubah' => 'info', 'Hapus' => 'danger', 'Batalkan' => 'warn', 'Unduh' => 'neutral', 'Login' => 'success', 'Login gagal' => 'danger', 'Logout' => 'neutral', 'Reset data contoh' => 'danger']; @endphp
    {{-- Log hanya dibaca: tanpa tombol Data Baru (new-record null). Di HP: satu kartu per aktivitas (user + waktu, lencana Aksi, IP, rincian). --}}
    <x-dl2::grid id="activity-log" actions="none" :new-record="null" :rows="$rows" search="Cari user, IP, aksi, rincian..."
        :phone-cards="['title' => 'user', 'sub' => 'at', 'badge' => 'action', 'tones' => $tones, 'meta' => ['ip', 'loc', 'detail']]"
        :columns="[
            ['key' => 'at', 'label' => 'Waktu'],
            ['key' => 'user', 'label' => 'User'],
            ['key' => 'ip', 'label' => 'IP'],
            ['key' => 'loc', 'label' => 'Lokasi (perkiraan)'],
            ['key' => 'action', 'label' => 'Aksi', 'type' => 'badge', 'tones' => $tones],
            ['key' => 'detail', 'label' => 'Rincian'],
        ]" />
    </div>
@endsection
