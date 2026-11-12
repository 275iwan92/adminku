{{-- REMARK
 Tujuan       : Kepala layar satu Stok Opname: nomor + status, ringkasan (tanggal, cakupan, cut-off, ambang, hitung buta, kunci), pesan penting, dan tombol aksi (Supervisor: Buka kunci, Batalkan).
 Sumber hulu  : StockCountController@show (count, sup, summary).
 Input        : count, sup, summary.
 Output       : x-dl2::headline, x-dl2::kpi-row, x-dl2::alert, x-dl2::action-bar (cetak lembar hitung / Berita Acara; POST stock-counts.unlock / stock-counts.cancel).
 Dipakai oleh : stock-counts/show.
 Status       : Aktif.
--}}
@php
    use Devlib2\Locale;
    $tone = ['counting' => 'warn', 'review' => 'info', 'done' => 'success', 'cancelled' => 'danger'][$count->status] ?? 'neutral';
    $running = in_array($count->status, ['counting', 'review'], true);
    $lockText = match (true) {
        ! $running => 'Dilepas',
        $count->lock_transactions => 'Aktif',
        $count->unlocked_at !== null => 'Dibuka',   // kunci sempat dibuka dan tidak dikunci lagi
        default => 'Tanpa kunci',
    };
@endphp
<x-dl2::headline><x-slot:title>Stok Opname · {{ $count->doc_no }} <x-dl2::badge :tone="$tone" :label="raw($count->statusLabel())" /></x-slot:title></x-dl2::headline>
<x-dl2::kpi-row>
    <x-dl2::stat-box label="Count Date" :value="Locale::d($count->count_date)" tone="neutral" />
    <x-dl2::stat-box label="Scope" :value="$count->scopeLabel()" tone="neutral" :sub="'Cut-off '.Locale::stamp($count->cutoff_at)" />
    {{-- Sedang dihitung: jumlah terisi tampil hidup di kotak Terisi / Belum diisi pada lembar, bukan angka beku di sini. --}}
    @if ($count->status !== 'counting')
        <x-dl2::stat-box label="Filled" :value="$summary['filled'].' / '.$summary['lines']" :tone="$summary['filled'] === $summary['lines'] ? 'success' : 'accent'" :sub="'Oleh '.$count->creator->name" />
    @endif
    <x-dl2::stat-box label="Difference Threshold" :value="Locale::number($count->threshold_pct, 2).' %'" tone="neutral" :sub="$count->blind ? 'Hitung buta: ya' : 'Hitung buta: tidak'" />
    <x-dl2::stat-box label="Transaction Lock" :value="$lockText" :tone="$running && $count->lock_transactions ? 'danger' : 'neutral'" :sub="$count->lock_transactions && $count->locked_at ? 'Dikunci '.$count->locker?->name.' · '.Locale::stamp($count->locked_at) : ($count->unlocked_at ? 'Dibuka '.$count->unlocker?->name.' · '.Locale::stamp($count->unlocked_at) : null)" />
</x-dl2::kpi-row>
@if ($running && $count->lock_transactions)
    <x-dl2::alert tone="warn" message="Transaksi dikunci: Confirm / Approve Penerimaan, Pengeluaran, dan Penyesuaian ditolak sampai opname selesai atau dibatalkan." />
@endif
@if ($count->status === 'counting' && $count->review_note)
    <x-dl2::alert tone="info" :message="raw('Dikembalikan Supervisor untuk dihitung ulang: '.$count->review_note)" />
@endif
@if ($count->status === 'done')
    <x-dl2::alert tone="success" :message="raw($count->adjustmentDocument ? 'Disetujui '.Locale::stamp($count->approved_at).'. Penyesuaian otomatis: '.$count->adjustmentDocument->doc_no.'; stok sudah mengikuti hasil hitung fisik.' : 'Disetujui '.Locale::stamp($count->approved_at).'. Tidak ada selisih, jadi tidak ada Penyesuaian.')" />
@endif
@if ($count->status === 'cancelled')
    <x-dl2::alert tone="danger" :message="raw('Dibatalkan '.Locale::stamp($count->cancelled_at).'. Stok tidak berubah.')" />
@endif
<x-dl2::action-bar align="start">
    <x-dl2::button variant="secondary" icon="back" label="Back" :href="route('stock-counts.index')" />
    <x-dl2::button variant="secondary" icon="print" label="Print Count Sheet" :href="route('stock-counts.sheet', $count)" target="_blank" />
    @if ($sup && in_array($count->status, ['review', 'done'], true))
        <x-dl2::button variant="secondary" icon="print" :label="$count->status === 'done' ? 'Print Minutes' : 'Preview Minutes'" :href="route('stock-counts.report', $count)" target="_blank" />
    @endif
    @if ($running && $sup)
        @unless ($count->lock_transactions)
            <x-dl2::action-form :action="route('stock-counts.lock', $count)">
                <x-dl2::button type="submit" variant="secondary" icon="fa-lock" label="Lock" data-dl2-confirm="Kunci transaksi lagi? Confirm / Approve Penerimaan, Pengeluaran, dan Penyesuaian ditolak sampai opname selesai atau dibatalkan." data-dl2-confirm-tone="warn" />
            </x-dl2::action-form>
        @endunless
        @if ($count->lock_transactions)
            <x-dl2::action-form :action="route('stock-counts.unlock', $count)">
                <x-dl2::button type="submit" variant="secondary" icon="fa-lock-open" label="Unlock" data-dl2-confirm="Buka kunci transaksi? Transaksi stok boleh diproses lagi selama opname berjalan; yang menggeser stok akan ditolak saat persetujuan." data-dl2-confirm-tone="warn" />
            </x-dl2::action-form>
        @endif
        <x-dl2::action-form :action="route('stock-counts.cancel', $count)">
            <x-dl2::button type="submit" variant="danger" icon="fa-ban" label="Cancel Count" data-dl2-confirm="Batalkan Stok Opname ini? Kunci dilepas dan stok tidak berubah." data-dl2-confirm-tone="warn" />
        </x-dl2::action-form>
    @endif
</x-dl2::action-bar>
