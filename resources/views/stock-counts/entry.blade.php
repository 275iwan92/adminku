{{-- REMARK
 Tujuan       : Lembar INPUT hasil hitung (Stok Opname): satu layar massal, satu baris per batch. Isian Hasil hitung (kosong = belum dihitung, 0 = memang kosong), Penghitung, Tidak dihitung (+ Catatan wajib) tersimpan otomatis per sel.
 Sumber hulu  : StockCountController@show (count, entryRows). Hitung buta: stok sistem tidak dikirim ke browser sama sekali.
 Input        : count, entryRows.
 Output       : x-dl::lookup-grid (auto-save ke stock-counts.lines; kotak Terisi / Belum diisi dihitung langsung di browser; baris selisih besar berwarna bila opname tidak buta), tombol Simpan Cadangan (POST stock-counts.save lewat app.js, memastikan semua isian sudah di server),
                modal Tambah barang (POST stock-counts.items), tombol Selesai hitung (POST stock-counts.finish).
 Dipakai oleh : stock-counts/show (status Sedang dihitung; status lain hanya baca).
 Status       : Aktif.
--}}
@php
    $readonly = $count->status !== 'counting';
    $edit = fn (array $c) => $c + ['readonly' => $readonly];
@endphp
@if ($readonly)
    <x-dl::alert tone="info" message="Penghitungan sudah ditutup: lembar ini hanya bisa dibaca." />
@else
    <x-dl::alert tone="info" message="Kosong = belum dihitung; isi 0 bila barang memang kosong. Tidak ketemu / tidak bisa dihitung? Isi Catatan, lalu centang Tidak dihitung. Setiap perubahan tersimpan otomatis." />
    <x-dl::action-bar align="start">
        <x-dl::button variant="secondary" icon="new" label="Add Item" data-dl-open="add-item-modal" />
        <x-dl::button variant="secondary" icon="save" label="Save Backup" data-count-save="{{ route('stock-counts.save', $count) }}" />
        <x-dl::action-form :action="route('stock-counts.finish', $count)">
            <x-dl::button type="submit" icon="fa-flag-checkered" label="Finish Count" data-dl-confirm="Selesai hitung? Semua baris harus terisi (atau Tidak dihitung dengan catatan). Sesudah ini hasil tidak bisa diubah kecuali dikembalikan Supervisor." />
        </x-dl::action-form>
    </x-dl::action-bar>
@endif
<x-dl::lookup-grid :rows="$entryRows" :add-rows="false" :auto-save="true" :save-url="route('stock-counts.lines', $count)" :row-tone="$count->blind ? null : 'tone'"
    :stats="[['label' => 'All'], ['label' => 'Filled', 'where' => ['filled' => 'yes'], 'tone' => 'success'], ['label' => 'Not filled', 'where' => ['filled' => 'no'], 'tone' => 'warn']]"
    :columns="array_values(array_filter([
        ['key' => 'code', 'label' => 'Code', 'readonly' => true, 'width' => '130px'],
        ['key' => 'name', 'label' => 'Item Name', 'readonly' => true, 'width' => '240px'],
        ['key' => 'batch', 'label' => 'Expiry', 'readonly' => true, 'width' => '110px'],
        ['key' => 'location', 'label' => 'Location', 'readonly' => true, 'width' => '100px'],
        ['key' => 'unit', 'label' => 'Unit', 'readonly' => true, 'width' => '70px'],
        $count->blind ? null : ['key' => 'system_qty', 'label' => 'System Stock', 'type' => 'number', 'decimal' => true, 'readonly' => true, 'width' => '110px'],
        $count->blind ? null : ['key' => 'diff', 'label' => 'Difference', 'type' => 'number', 'decimal' => true, 'readonly' => true, 'width' => '100px'],
        $edit(['key' => 'counted_qty', 'label' => 'Count Result', 'type' => 'number', 'decimal' => true, 'width' => '120px']),
        $edit(['key' => 'counted_by', 'label' => 'Counter', 'width' => '140px']),
        $edit(['key' => 'skipped', 'label' => 'Not counted', 'type' => 'check', 'width' => '100px']),
        $edit(['key' => 'note', 'label' => 'Note', 'width' => '220px']),
    ]))" />
@unless ($readonly)
    <x-dl::modal id="add-item-modal" title="Add Item" icon="new" size="sm">
        <form id="add-item-form" method="POST" action="{{ route('stock-counts.items', $count) }}">
            @csrf
            <p class="dl-note">Pilih barang / batch yang ditemukan fisik tetapi tidak ada di lembar. Stok sistemnya diambil saat ini.</p>
            <x-dl::field.lookup name="batch_id" lookup="item_count" label="Item" required />
        </form>
        <x-slot:footer><x-dl::button type="submit" form="add-item-form" icon="save" label="Add Item" /></x-slot:footer>
    </x-dl::modal>
@endunless
