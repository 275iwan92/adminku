{{-- REMARK
 Tujuan       : Setup Perawatan AC (GA, modul GAM): kumpulan ubin ke layar master dan pengaturan: Denah AC, Master AC, Area dan Gedung, Ruang, Vendor AC, Pengaturan GA. Tiap ubin tampil hanya bila pengguna punya hak modulnya.
 Sumber hulu  : AcCareController@setup; hak lewat User::canAccess.
 Input        : tidak ada.
 Output       : tautan ke ac-plan, ac-units, ac-areas, ac-rooms, ac-vendors, ga-settings.
 Dipakai oleh : tombol Setup di layar Monitoring AC (ac-care.index).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Setup Perawatan AC'])
@section('body')
    @php
        $u = auth()->user();
        $tiles = [
            ['GAD', 'ac-plan.index', 'fa-map-location-dot', 'Denah AC', 'Posisi AC pada denah tiap area'],
            ['GAK', 'ac-units.index', 'fa-snowflake', 'Master AC', 'Kode aset, merek, ruang, riwayat awal'],
            ['GAA', 'ac-areas.index', 'fa-building', 'Area dan Gedung', 'Gedung, lantai, gambar denah'],
            ['GAR', 'ac-rooms.index', 'fa-door-open', 'Ruang', 'Kode ruang dan PIC'],
            ['GAE', 'ac-vendors.index', 'fa-handshake', 'Vendor AC', 'Kontak, e-mail, WhatsApp'],
            ['GAP', 'ga-settings.edit', 'fa-sliders', 'Pengaturan AC', 'Interval cuci dan servis, hari, jam, kapasitas'],
        ];
    @endphp
    @include('partials.flash')
    <x-dl::action-bar align="start">
        <x-dl::button class="sstock-btn-slate" variant="secondary" icon="fa-arrow-left" label="Kembali" :href="route('ac-care.index')" />
    </x-dl::action-bar>
    <div class="sstock-setup">
        @foreach ($tiles as [$module, $route, $icon, $label, $text])
            @if ($u->canAccess($module))
                <a class="sstock-setup__tile" href="{{ route($route) }}">
                    <i class="fa-solid {{ $icon }}" aria-hidden="true"></i>
                    <b>{{ $label }}</b>
                    <small>{{ $text }}</small>
                </a>
            @endif
        @endforeach
    </div>
@endsection
