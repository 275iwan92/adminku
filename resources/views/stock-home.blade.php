{{-- REMARK
 Tujuan       : Beranda Stok (modul STB): isi Beranda lama, dibuka sebagai tab Layer Shell: pengingat Stok Opname, angka ringkasan stok, barang di titik pesan ulang, barang hampir kedaluwarsa, grafik nilai stok.
 Sumber hulu  : StockHomeController (daftar barang di / bawah titik pesan ulang dan yang hampir kedaluwarsa, angka ringkasan), partial partials.count-reminder, partials.reorder-alert, partials.expiry-alert.
 Input        : below, countReminder, canStartCount, leadTimes, suppliers, expiring, itemCount, stockValue, receivingToday, issuanceToday, valuation, pending.
 Output       : kpi-row, tabel peringatan, grafik; kotak dan tombol ber-data-open-tab membuka menu yang sama sebagai tab kerja (pesan open-tab ke Shell induk).
 Dipakai oleh : route stock.home.
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Beranda Stok'])
@section('body')
    @include('partials.flash')
    @include('partials.count-reminder')
    <x-dl::kpi-row>
        <x-dl::kpi label="Active items" :value="$itemCount" icon="fa-boxes-stacked" data-open-tab="ITM" class="sstock-link" role="link" tabindex="0" />
        <x-dl::kpi label="At / below reorder point" :value="$below->count()" icon="fa-triangle-exclamation" :tone="$below->isNotEmpty() ? 'danger' : 'success'" good-when="down" data-open-tab="ITM" class="sstock-link" role="link" tabindex="0" />
        <x-dl::kpi label="Near expiry / expired" :value="$expiring->count()" icon="fa-hourglass-end" :tone="$expiring->isNotEmpty() ? ($expiring->contains(fn ($i) => $i->expiryState() === 'KEDALUWARSA') ? 'danger' : 'warn') : 'success'" good-when="down" data-open-tab="R-EXP" class="sstock-link" role="link" tabindex="0" />
        <x-dl::kpi label="Receiving today" :value="$receivingToday" icon="fa-truck-ramp-box" data-open-tab="RCV" class="sstock-link" role="link" tabindex="0" />
        <x-dl::kpi label="Issuance today" :value="$issuanceToday" icon="fa-dolly" data-open-tab="ISS" class="sstock-link" role="link" tabindex="0" />
        @if ($stockValue !== null)
            <x-dl::kpi label="Stock value" :value="$stockValue" format="money" icon="fa-coins" data-open-tab="R-SUM" class="sstock-link" role="link" tabindex="0" />
        @endif
        @if ($pending !== null)
            <x-dl::kpi label="Documents waiting approval" :value="$pending" icon="fa-scale-balanced" :tone="$pending ? 'warn' : 'success'" good-when="down" />
        @endif
    </x-dl::kpi-row>
    <div class="sstock-split">
        @if ($below->isNotEmpty())
            <div>@include('partials.reorder-alert')</div>
        @endif
        @if ($expiring->isNotEmpty())
            <div>@include('partials.expiry-alert')</div>
        @endif
        @if ($valuation)
            <div class="sstock-box">
                <div class="sstock-box-nav">
                    @if ($valuation['prev'])
                        <x-dl::button variant="secondary" size="sm" icon="fa-chevron-left" label="Bulan lalu" :href="route('stock.home', ['month' => $valuation['prev']])" />
                    @else
                        <span></span>
                    @endif
                    <strong>{{ $valuation['title'] }}</strong>
                    @if ($valuation['next'])
                        <x-dl::button variant="secondary" size="sm" icon="fa-chevron-right" label="Bulan depan" :href="route('stock.home', ['month' => $valuation['next']])" />
                    @else
                        <span></span>
                    @endif
                </div>
                <x-dl::chart type="hbar" format="money" height="320px" :legend="false" title="Nilai Stok" subtitle="Per minggu"
                    :labels="$valuation['labels']" :series="[['name' => 'Nilai stok', 'data' => $valuation['values'], 'tone' => 'violet']]" source="rm_stockcard" />
            </div>
        @endif
    </div>
    {{-- Layar ini berjalan di dalam tab (iframe) Layer Shell: pesan open-tab dikirim ke jendela induk; di luar Shell (dibuka langsung) ke jendela sendiri. --}}
    <script>
        const openTab = el => (window.parent !== window ? window.parent : window).postMessage({dl: 'open-tab', code: el.dataset.openTab}, location.origin);
        document.addEventListener('click', e => { const el = e.target.closest('[data-open-tab]'); if (el) { e.preventDefault(); openTab(el); } });
        document.addEventListener('keydown', e => { const el = e.target.closest?.('[data-open-tab]'); if (el && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); openTab(el); } });
    </script>
@endsection
