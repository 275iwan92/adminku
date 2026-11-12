{{-- REMARK
 Tujuan       : Daftar modul satu kelompok (mis. Transaksi) sebagai kartu besar yang mudah disentuh; tiap kartu membuka modulnya.
 Sumber hulu  : HpController@group (title, items hasil HpShell::modules).
 Input        : title, items (code, label, url, icon, badge), active.
 Output       : kartu tautan, satu per modul yang boleh diakses.
 Dipakai oleh : route hp.group (tab Transaksi).
 Status       : Aktif.
--}}
@extends('hp.layout', ['back' => route('home')])
@section('content')
    @forelse ($items as $it)
        <x-dl2::phone-link :label="raw($it['label'])" :icon="$it['icon']" :url="$it['url']" :badge="$it['badge'] ?? null" />
    @empty
        <x-dl2::alert tone="info" message="Tidak ada modul yang bisa Anda buka di kelompok ini." />
    @endforelse
@endsection
