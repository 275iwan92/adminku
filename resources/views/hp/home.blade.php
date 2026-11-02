{{-- REMARK
 Tujuan       : Beranda tampilan HP: banner hero (partial home-hero), ringkasan stok sebagai kartu besar (angka + nama) yang membuka modulnya, dan pengingat Stok Opname.
 Sumber hulu  : HpController@home (cards, countReminder).
 Input        : hero (HomeHero::build), cards (label, value, icon, code, tone), countReminder (CountSchedule::state atau null).
 Output       : kartu tautan ke hp.open / hp.counts, pengingat opname.
 Dipakai oleh : HomeController bila perangkat = hp.
 Status       : Aktif.
--}}
@extends('hp.layout')
@section('content')
    @include('partials.flash')
    @include('partials.home-hero', ['hero' => $hero])
    @if ($countReminder)
        @php $r = $countReminder; @endphp
        <x-dl::phone-link icon="fa-solid fa-clipboard-check" :url="route('hp.counts')" :tone="$r['running'] ? 'info' : ($r['tone'] === 'neutral' ? 'info' : $r['tone'])"
            :label="raw($r['running'] ? 'Stok Opname '.$r['running']->doc_no.' sedang berjalan ('.$r['running']->statusLabel().', terisi '.$r['running_progress'].')' : $r['headline'].' '.$r['detail'])" />
    @endif
    @if (auth()->user()->canAccess('ITM'))
        <x-dl::phone-link icon="fa-solid fa-magnifying-glass" :url="route('hp.find')" label="Cari Barang" />
    @endif
    <x-dl::phone-tiles>
        @foreach ($cards as $c)
            <x-dl::phone-tile :label="raw($c['label'])" :value="\Devlib\Locale::number($c['value'], 0)" :icon="$c['icon']" :url="\App\Support\HpShell::url($c['code'])" :tone="$c['tone'] ?? ''" />
        @endforeach
    </x-dl::phone-tiles>
@endsection
