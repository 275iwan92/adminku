{{-- REMARK
 Tujuan       : Setup Perawatan Gedung dan Aset (GA, modul FAM): ubin ke layar master: Aset, Gedung dan Ruang, Kategori Aset, Vendor (memakai master Vendor AC yang sama). Tiap ubin tampil hanya bila pengguna punya hak modulnya.
 Sumber hulu  : FacCareController@setup; hak lewat User::canAccess.
 Input        : tidak ada.
 Output       : tautan ke fac-assets, fac-places, fac-categories, ac-vendors.
 Dipakai oleh : tombol Setup di dashboard fac-care.index.
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Setup Perawatan Gedung dan Aset'])
@section('body')
    @php
        $u = auth()->user();
        $tiles = [
            ['FAA', 'fac-assets.index', 'fa-boxes-stacked', 'Aset', 'Kode otomatis, kategori, lokasi, garansi, kondisi'],
            ['FAG', 'fac-places.index', 'fa-building', 'Gedung dan Ruang', 'Gedung dan ruang / area di dalamnya'],
            ['FAC', 'fac-categories.index', 'fa-tags', 'Kategori Aset', 'Bangunan, listrik, plumbing, mebel, ...'],
            ['GAE', 'ac-vendors.index', 'fa-handshake', 'Vendor', 'Vendor perawatan (sama dengan Vendor AC)'],
        ];
    @endphp
    @include('partials.flash')
    <x-dl::action-bar align="start">
        <x-dl::button class="sstock-btn-slate" variant="secondary" icon="fa-arrow-left" label="Kembali" :href="route('fac-care.index')" />
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
