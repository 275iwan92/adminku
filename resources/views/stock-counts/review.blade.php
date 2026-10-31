{{-- REMARK
 Tujuan       : Layar TINJAU selisih Stok Opname (Supervisor): sistem vs fisik per batch, selisih jumlah / persen / nilai (selisih x biaya rata-rata saat cut-off), Hitung ulang dan Alasan per baris
                (alasan WAJIB bila selisih melewati ambang), ringkasan lebih / kurang, lalu Setujui (mesin membuat satu Penyesuaian), Kembalikan, atau Batalkan.
 Sumber hulu  : StockCountController@show (count, summary, reviewRows, moved).
 Input        : count, summary, reviewRows, moved.
 Output       : x-dl::lookup-grid (auto-save ke stock-counts.review; halaman dimuat ulang setelah simpan supaya ringkasan ikut benar; baris selisih besar berwarna), POST stock-counts.approve / reject / refresh (semua baris bergeser) / refresh-line (satu baris).
 Dipakai oleh : stock-counts/show (Supervisor, status Tinjau; Selesai / Dibatalkan hanya baca).
 Status       : Aktif.
--}}
@php
    use Devlib\Locale;
    $editable = $count->status === 'review';
    $money = fn (float $v) => Locale::money($v, 'IDR', 0);
    $edit = fn (array $c) => $c + ['readonly' => ! $editable];
@endphp
@if ($moved->isNotEmpty())
    <x-dl::alert tone="danger" :message="raw('Stok bergeser sejak cut-off pada '.$moved->count().' baris selisih ('.$moved->take(8)->map(fn ($l) => $l->batch->code.': sistem '.Locale::number($l->system_qty, 3).', kini '.Locale::number($l->batch->stock_qty, 3))->implode('; ').($moved->count() > 8 ? '; ...' : '').'). Persetujuan akan ditolak sampai baris ini diperbarui dan dihitung ulang.')">
        <x-dl::action-form :action="route('stock-counts.refresh', $count)">
            <x-dl::button type="submit" size="sm" icon="fa-rotate" label="Refresh Moved Lines" data-dl-confirm="Perbarui baris yang bergeser? Stok sistemnya diganti stok sekarang dan barisnya dikosongkan untuk dihitung ulang; opname kembali ke Sedang dihitung." data-dl-confirm-tone="warn" />
        </x-dl::action-form>
        <x-dl::action-bar align="start">
            @foreach ($moved->take(12) as $l)
                <x-dl::action-form :action="route('stock-counts.refresh-line', [$count, $l->id])">
                    <x-dl::button type="submit" size="sm" variant="secondary" icon="fa-rotate" :label="'Perbarui '.$l->batch->code" data-dl-confirm="Perbarui baris {{ $l->batch->code }} saja? Stok sistemnya diganti stok sekarang dan barisnya dikosongkan untuk dihitung ulang; opname kembali ke Sedang dihitung." data-dl-confirm-tone="warn" />
                </x-dl::action-form>
            @endforeach
        </x-dl::action-bar>
    </x-dl::alert>
@endif
@if ($editable && ($ownShare ?? 0) > 0.5)
    <x-dl::alert tone="warn" :message="raw('Anda sendiri yang menginput '.round($ownShare * 100).' persen baris opname ini. Disarankan penyetuju bukan penginput (hanya peringatan, tetap boleh disetujui).')" />
@endif
@if ($editable)
    <x-dl::alert tone="info" :message="raw('Selisih di atas '.Locale::number($count->threshold_pct, 2).' persen (kolom Besar) wajib diberi Alasan sebelum disetujui. Hitung ulang: isi jumlah hasil hitung ulang; bila ada, itu yang dipakai.')" />
@endif
<x-dl::kpi-row>
    <x-dl::stat-box label="Counted Batches" :value="(string) $summary['counted']" tone="neutral" :sub="'dari '.$summary['lines'].' baris'" />
    <x-dl::stat-box label="Rows with Difference" :value="(string) $summary['diffs']" :tone="$summary['diffs'] ? 'warn' : 'success'" :sub="$summary['over_lines'].' lebih · '.$summary['under_lines'].' kurang'" />
    <x-dl::stat-box label="Surplus Value" :value="$money($summary['over_value'])" tone="success" />
    <x-dl::stat-box label="Shortage Value" :value="$money($summary['under_value'])" tone="danger" />
    <x-dl::stat-box label="Net Value" :value="$money($summary['net_value'])" :tone="$summary['net_value'] < 0 ? 'danger' : 'accent'" />
</x-dl::kpi-row>
@if ($editable)
    <x-dl::action-bar align="start">
        <x-dl::action-form :action="route('stock-counts.approve', $count)">
            <x-dl::button type="submit" icon="fa-check" label="Approve Count" data-dl-confirm="Setujui Stok Opname? Stok akan diubah mengikuti hasil hitung fisik lewat satu Penyesuaian otomatis. Tidak bisa dibatalkan dari layar ini." data-dl-confirm-tone="warn" />
        </x-dl::action-form>
        <x-dl::button variant="secondary" icon="fa-rotate-left" label="Return to Counting" data-dl-open="return-modal" />
    </x-dl::action-bar>
@endif
<x-dl::lookup-grid data-reload-on-save :rows="$reviewRows" :add-rows="false" :auto-save="true" :save-url="route('stock-counts.review', $count)" row-tone="tone"
    :stats="[['label' => 'All'], ['label' => 'Differences only', 'where' => ['has_diff' => 'yes'], 'tone' => 'warn'], ['label' => 'Large differences', 'where' => ['big' => 'yes'], 'tone' => 'danger']]"
    :columns="[
        ['key' => 'code', 'label' => 'Code', 'readonly' => true, 'width' => '120px'],
        ['key' => 'name', 'label' => 'Item Name', 'readonly' => true, 'width' => '200px'],
        ['key' => 'batch', 'label' => 'Expiry', 'readonly' => true, 'width' => '100px'],
        ['key' => 'unit', 'label' => 'Unit', 'readonly' => true, 'width' => '60px'],
        ['key' => 'system_qty', 'label' => 'System Stock', 'type' => 'number', 'decimal' => true, 'readonly' => true, 'width' => '100px'],
        ['key' => 'physical', 'label' => 'Physical', 'type' => 'number', 'decimal' => true, 'readonly' => true, 'width' => '100px'],
        ['key' => 'diff', 'label' => 'Difference', 'type' => 'number', 'decimal' => true, 'readonly' => true, 'width' => '100px'],
        ['key' => 'pct', 'label' => 'Percent', 'type' => 'number', 'decimals' => 2, 'readonly' => true, 'width' => '80px'],
        ['key' => 'value', 'label' => 'Value Effect', 'type' => 'money', 'readonly' => true, 'width' => '120px'],
        ['key' => 'flag', 'label' => 'Large', 'readonly' => true, 'width' => '70px'],
        ['key' => 'state', 'label' => 'State', 'readonly' => true, 'width' => '120px'],
        ['key' => 'entered_by', 'label' => 'Entered By', 'readonly' => true, 'width' => '120px'],
        $edit(['key' => 'recount_qty', 'label' => 'Recount', 'type' => 'number', 'decimal' => true, 'width' => '100px']),
        $edit(['key' => 'reason', 'label' => 'Reason', 'width' => '240px']),
    ]" />
@if ($editable)
    <x-dl::modal id="return-modal" title="Return to Counting" icon="fa-rotate-left" size="sm">
        <form id="return-form" method="POST" action="{{ route('stock-counts.reject', $count) }}">
            @csrf
            <p class="dl-note">Opname kembali ke Sedang dihitung supaya isiannya diperbaiki; stok tidak berubah.</p>
            <x-dl::field.text name="note" label="Note" required maxlength="255" multiline />
        </form>
        <x-slot:footer><x-dl::button type="submit" form="return-form" icon="save" label="Return to Counting" /></x-slot:footer>
    </x-dl::modal>
@endif
