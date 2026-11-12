{{-- Kop lembar cetak (x-pdoc): perusahaan kiri, judul tengah, QR kanan. Dipakai lembar dokumen dan lembar lampiran. --}}
<header class="ak-pdoc__kop">
    @include('components.pdoc.co', ['co' => $co, 'npwp' => true])
    <h1 @class(['is-compact' => $compact ?? false])>{{ $title }}@if (! empty($subtitle))<small>{{ $subtitle }}</small>@endif</h1>
    <div class="ak-pdoc__kopr"><small>{{ $t('Scan for') }}<br>{{ $t($doc->qrHint) }}</small><span class="ak-pdoc__qr" data-pd-qr></span></div>
</header>
