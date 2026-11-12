{{-- Blok perusahaan pada kop lembar (print-doc, lampiran, print-sheet). Isi ditentukan Devlib\PrintDoc\Kop; $npwp = tampilkan NPWP. --}}
@php($k = \Devlib2\PrintDoc\Kop::of($co))
<div @class(['ak-pdoc__co', 'is-side' => $k['side'], 'is-styled' => $k['styled']])>
    @if ($k['badge'])<span class="ak-pdoc__logo">{{ $k['initials'] }}</span>@endif
    @if ($k['side'])<img class="ak-pdoc__brand" src="{{ $k['logoUrl'] }}" alt="{{ $k['nameText'] }}">@endif
    <div>@if ($k['logo'] && ! $k['side'])<img class="ak-pdoc__brand" src="{{ $k['logoUrl'] }}" alt="{{ $k['nameText'] }}">@endif @if ($k['name'])<b>{{ $k['nameText'] }}</b>@endif @if ($k['legal'])<small class="ak-pdoc__legal">{{ $k['legalText'] }}</small>@endif @if ($k['addr'])@foreach ($co['lines'] as $line)<small>{{ $line }}</small>@endforeach @endif @if ($npwp && $k['npwp'])<small>NPWP {{ $k['npwp'] }}</small>@endif</div>
</div>
