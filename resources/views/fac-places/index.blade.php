{{-- REMARK
 Tujuan       : Daftar master Gedung dan Ruang (modul FAG): satu kartu per gedung yang bisa dibuka; isinya kartu ruang / area dengan jumlah aset aktif.
 Sumber hulu  : FacPlaceController@index (buildings).
 Input        : buildings.
 Output       : partial fac-cardlist; klik kartu membuka form Gedung / Ruang.
 Dipakai oleh : route fac-places.index (Setup Perawatan Gedung dan Aset).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Gedung dan Ruang'])
@section('body')
    @php
        $groups = collect($buildings)->map(fn ($b) => [
            'label' => $b['name'], 'icon' => 'fa-building', 'unit' => 'ruang', 'empty' => 'Belum ada ruang di gedung ini.',
            'items' => collect($b['rooms'])->map(fn ($r) => [
                'url' => url('fac-places/'.$r['id']), 'title' => $r['name'], 'code' => $r['code'], 'off' => ! $r['active'], 'pills' => [[$r['assets'].' aset', $r['assets'] ? 'ok' : 'plain']],
            ])->all(),
            'head' => $b,
        ])->all();
    @endphp
    @include('partials.flash')
    @include('partials.back-to-hub', ['hub' => 'fac'])
    <p class="ak-note">Klik kelompok gedung untuk melihat ruangnya. Tombol di bawah membuka gedung atau menambah ruang.</p>
    <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px">
        @foreach ($buildings as $b)
            <x-dl2::button variant="secondary" size="sm" icon="fa-pen" :label="$b['code'].' · '.$b['name'].' ('.$b['assets'].' aset)'.($b['active'] ? '' : ' · nonaktif')" :href="url('fac-places/'.$b['id'])" />
            <x-dl2::button variant="secondary" size="sm" icon="fa-plus" :label="'Ruang di '.$b['name']" :href="route('fac-places.create', ['gedung' => $b['id']])" />
        @endforeach
    </div>
    @include('partials.fac-cardlist', ['groups' => $groups, 'newUrl' => route('fac-places.create'), 'searchHint' => 'Cari kode atau nama ruang...'])
@endsection
