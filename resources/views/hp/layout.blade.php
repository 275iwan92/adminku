{{-- REMARK
 Tujuan       : Kerangka semua layar tampilan HP AdminKU: x-dl::phone-shell (bilah atas dengan satu tombol hamburger; panel menu: menu utama Beranda / Stok / Transaksi / Opname / Laporan) dengan isi dari @yield('content').
 Sumber hulu  : App\Support\HpShell (tab, modul lembar Menu menurut akses pengguna).
 Input        : title, back (alamat tombol kembali, kosong = layar utama), active (kunci tab), frame (alamat layar desktop yang dibuka di dalam kerangka).
 Output       : halaman penuh dengan x-dl::phone-shell; pilihan Tampilan HP / Desktop dan Keluar ada di lembar Menu.
 Dipakai oleh : hp.home, hp.group, hp.frame, hp.counts, hp.count, hp.find, hp.find.show (@extends).
 Status       : Aktif.
--}}
@extends('layout', ['title' => $title ?? config('app.name'), 'bodyClass' => 'sstock-bare sstock-hp'])
@section('body')
    @php $me = auth()->user(); @endphp
    <x-dl::phone-shell :title="$title ?? config('app.name')" :back="$back ?? null" :active="$active ?? ''" :tabs="\App\Support\HpShell::tabs($me)" :modules="\App\Support\HpShell::modules($me)"
        :user="['name' => $me->name, 'role' => $me->roleLabel()]" :logout-action="route('logout')" logout-top :device-action="\App\Support\HpShell::deviceAction()" :links="array_values(array_filter([$me->canAccess('ITM') ? ['label' => 'Cari Barang', 'icon' => 'fa-solid fa-magnifying-glass', 'url' => route('hp.find')] : null, ['label' => 'Bantuan', 'icon' => 'fa-solid fa-circle-question', 'url' => route('help.user-guide')]]))" :frame="$frame ?? null" auto-return>
        @yield('content')
    </x-dl::phone-shell>
@endsection
