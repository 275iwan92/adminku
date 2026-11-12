{{-- REMARK
 Tujuan       : Form master Barang (Single Form kind=master): kotak stok baca-saja, nama besar, data umum, parameter pesan ulang + harga, dan daftar batch (tanggal kedaluwarsa, stok) barang itu.
 Sumber hulu  : ProductController (product, batches, categories, units, locations, nextCodes). Biaya rata-rata, nilai stok, dan harga hanya untuk Supervisor.
 Input        : product, batches, categories, units, locations, nextCodes.
 Output       : POST products.store (baru, semua pengguna) / PUT products.update (ubah, Activate, Deactivate: Supervisor).
 Dipakai oleh : routes products.create, products.show.
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Item'])
@section('body')
    @php
        use Devlib2\Locale;
        $sup = auth()->user()->isSupervisor();
        $isNew = ! $product->exists;
        $canInput = $sup || $isNew;
        $total = (float) $batches->sum('stock_qty');
        $available = $isNew ? 0 : $product->available();
        $below = ! $isNew && $product->isBelowReorderPoint();
        $v = fn (string $k) => old($k, $product->{$k});
        $e = fn (string $k) => $errors->first($k) ?: null;
    @endphp
    @include('partials.flash')
    <x-dl2::form :title="$isNew ? 'Barang · Baru' : 'Barang · '.raw($product->code)" :subtitle="$isNew ? null : raw($product->name)" panel="top" kind="master"
        :status="$isNew ? 'new' : ($product->is_active ? 'active' : 'inactive')" :can-input="$canInput" :hide="$sup ? [] : ['activate', 'deactivate']" :back="route('products.index')" :new-url="route('products.create')"
        :action="$isNew ? route('products.store') : route('products.update', $product)" :method="$isNew ? 'post' : 'put'"
        :created-at="$product->created_at?->format('Y-m-d H:i:s')" :updated-at="$product->updated_at?->format('Y-m-d H:i:s')">
        @unless ($isNew)
            <x-dl2::kpi-row>
                <x-dl2::stat-box label="Stock on hand" :value="Locale::number($total, 2).' '.$product->unit" :tone="$below ? 'danger' : 'accent'" :sub="'Tersedia '.Locale::number($available, 2).' · titik pesan ulang '.Locale::number($product->reorder_point, 2).' · '.($below ? 'PESAN ULANG' : 'AMAN')" />
                @if ($sup)
                    <x-dl2::stat-box label="Stock value" :value="Locale::money($batches->sum(fn ($b) => $b->stockValue()), 'IDR', 0)" tone="success" sub="Stok x biaya rata-rata per batch" />
                @endif
            </x-dl2::kpi-row>
            @if ($below && $product->openOrder)
                <x-dl2::alert tone="info" :message="raw($product->openOrder->note())" />
            @endif
        @endunless
        <x-dl2::divider label="Item" />
        <x-dl2::form.row :cols="1">
            <x-dl2::field.text class="sstock-name" name="name" label="Item Name" :value="$v('name')" required maxlength="150" :error="$e('name')" />
        </x-dl2::form.row>
        <x-dl2::form.row :cols="4">
            <x-dl2::field.text name="code" label="Item Code" :value="$product->code" code :case-toggle="false" readonly tooltip="Generated automatically: category prefix and running number. The expiry date is never part of the item code; each expiry batch has its own batch code." />
            <x-dl2::field.select name="category_id" label="Category" :options="$categories" :value="$v('category_id')" required :readonly="! $isNew" :add="\App\Support\QuickAdd::for('category')" :error="$e('category_id')" />
            <x-dl2::field.select name="unit" label="Unit" :options="$units" :value="$v('unit')" required :add="\App\Support\QuickAdd::for('unit')" :error="$e('unit')" />
            <x-dl2::field.select name="location" label="Rack / Storage Location" :options="$locations" :value="$v('location')" :add="\App\Support\QuickAdd::for('location')" :error="$e('location')" />
        </x-dl2::form.row>
        <x-dl2::form.row :cols="4">
            @if ($isNew)
                <x-dl2::field.checkbox name="tracks_expiry" label="Expiry" text="Item has an expiry date" :value="old('tracks_expiry', false)" tooltip="Tick for items that expire. The expiry date is then entered on every Receiving / Opening Balance line and each date becomes its own batch. Cannot be changed after saving." />
            @else
                <x-dl2::field.display label="Expiry" :value="$product->tracks_expiry ? 'Berkedaluwarsa (stok per batch)' : 'Tidak berkedaluwarsa'" />
            @endif
        </x-dl2::form.row>
        @if ($sup || ! $isNew)
            <x-dl2::divider :label="$sup ? 'Reorder and Price' : 'Reorder'" />
            <x-dl2::form.row :cols="4">
                <x-dl2::field.decimal name="reorder_point" label="Reorder Point" :decimals="2" :value="$v('reorder_point') ?? 0" required :error="$e('reorder_point')" tooltip="Alert shows when the available stock (all batches that are not expired) is at or below this quantity. 0 = no alert." />
                <x-dl2::field.decimal name="reorder_qty" label="Reorder Qty" :decimals="2" :value="$v('reorder_qty') ?? 0" required :error="$e('reorder_qty')" tooltip="Suggested quantity to order." />
                <x-dl2::field.number name="lead_time_days" label="Lead Time (days)" :min="0" :max="365" :value="$v('lead_time_days') ?? 0" required :error="$e('lead_time_days')" tooltip="Days from placing the order until the supplier delivers." />
                @if ($sup)
                    <x-dl2::field.decimal name="unit_price" label="Unit Price (IDR)" :decimals="2" :value="$v('unit_price') ?? 0" required :error="$e('unit_price')" tooltip="Reference price. Stock valuation uses the moving average of purchase prices." />
                @endif
            </x-dl2::form.row>
            @unless ($isNew)
                @if ($leadTime->source() === 'data')
                    <x-dl2::hint>Lead time otomatis: {{ $leadTime->effective() }} hari (rata-rata dari {{ $leadTime->basis() }} pemesanan). Isian Lead Time di atas tetap manual dan tidak berubah sendiri.</x-dl2::hint>
                @endif
                @include('products._reorder-advice', ['advice' => \App\Services\ReorderAdvice::advise($leadTime->effective(), $dailyUsage), 'unit' => $product->unit, 'canApply' => $sup])
            @endunless
            <p class="ak-note">Peringatan muncul bila stok tersedia (batch yang belum kedaluwarsa) sama dengan atau di bawah titik pesan ulang (0 = tanpa peringatan). Harga satuan hanya acuan; valuasi memakai rata-rata bergerak harga pembelian.</p>
        @else
            <p class="ak-note">Harga dan titik pesan ulang diisi Supervisor setelah barang disimpan.</p>
        @endif
        @unless ($isNew)
            <x-dl2::divider label="Batches" />
            <div class="dl2-table-responsive ak-stack">
                <table class="dl2-table is-striped">
                    <thead><tr>@foreach (['Batch Code', 'Expiry Date', 'Stock', 'Status'] as $c)<th>{{ \Devlib2\Lang::text($c) }}</th>@endforeach</tr></thead>
                    <tbody>
                        @foreach ($batches as $b)
                            @php($state = $b->expiryState())
                            <tr>
                                <td data-label="{{ \Devlib2\Lang::text('Batch Code') }}">{{ $b->code }}</td>
                                <td data-label="{{ \Devlib2\Lang::text('Expiry Date') }}">{{ $b->expiry_date ? Locale::d($b->expiry_date) : '—' }}</td>
                                <td data-label="{{ \Devlib2\Lang::text('Stock') }}">{{ Locale::number($b->stock_qty, 2) }} {{ $b->unit }}</td>
                                <td data-label="{{ \Devlib2\Lang::text('Status') }}">
                                    @if ($state === 'KEDALUWARSA')
                                        <x-dl2::badge tone="danger" icon="fa-triangle-exclamation" label="Kedaluwarsa" />
                                    @elseif ($state === 'HAMPIR')
                                        <x-dl2::badge tone="warn" icon="fa-hourglass-half" label="Hampir kedaluwarsa" />
                                    @else
                                        <x-dl2::badge tone="success" label="Baik" />
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($product->tracks_expiry)
                <p class="ak-note">Batch baru dibuat otomatis saat Penerimaan atau Saldo Awal mencatat tanggal kedaluwarsa yang belum ada. Pengeluaran mengambil batch yang kedaluwarsanya paling cepat lebih dulu.</p>
            @endif
            <x-dl2::divider label="Satuan masuk" />
            <p class="ak-note">Kemasan yang sah saat barang ini DITERIMA, mis. 1 dus = 12 {{ $product->unit }}. Penerimaan menawarkan satuan stok ({{ $product->unit }}) dan satuan di bawah ini; stok selalu dalam {{ $product->unit }}. Pengeluaran dan Penyesuaian selalu dalam {{ $product->unit }}.</p>
            <x-dl2::table :columns="['unit' => 'Satuan masuk', 'factor' => 'Faktor ke '.$product->unit, 'used' => 'Dipakai', 'by' => 'Diubah oleh', 'act' => '']" :rows="$packRows" :stack="true" empty="Belum ada satuan masuk: barang diterima dalam satuan stok atau satuan yang punya konversi umum." />
            <x-dl2::button type="button" variant="secondary" size="sm" icon="new" label="Tambah Satuan Masuk" data-dl2-toggle="modal" data-dl2-target="#pack-unit" />
        @endunless
    </x-dl2::form>
    @unless ($isNew)
        <x-dl2::modal id="pack-unit" title="Satuan masuk" icon="fa-boxes-packing" size="sm">
            <x-dl2::field.select name="pack_unit" label="Satuan" :options="$packChoices" required :add="\App\Support\QuickAdd::for('unit')" />
            <x-dl2::field.text name="pack_factor" :label="'Faktor ke '.$product->unit" placeholder="Contoh: 12" required :hint="'1 satuan ini = berapa '.$product->unit" />
            <p class="ak-note" data-pack-used hidden>Faktor ini sudah dipakai di dokumen. Perubahan hanya berlaku untuk penerimaan berikutnya, dokumen lama tidak berubah.</p>
            <p class="sstock-quickadd__err" data-pack-error hidden></p>
            <x-slot:footer><x-dl2::button type="button" icon="save" label="Simpan" data-pack-save /></x-slot:footer>
        </x-dl2::modal>
        <script>
            // Dialog dipindah ke <body> (di luar <form> barang): isiannya tidak ikut tersimpan bersama data barang.
            window.akReady(() => {
                const layer = document.querySelector('#pack-unit');
                document.body.append(layer);
                const rows = @json($packJs), url = @json(route('products.units.save', $product)), token = @json(csrf_token());
                const select = layer.querySelector('[data-dl2-select]'), factor = layer.querySelector('[name="pack_factor"]'), used = layer.querySelector('[data-pack-used]'), err = layer.querySelector('[data-pack-error]');
                const open = unit => {
                    const row = unit ? rows[unit] : null;
                    err.hidden = true;
                    used.hidden = !(row && row.used > 0);
                    factor.value = row ? row.factor : '';
                    select.querySelector(`[data-dl2-dd-list] [data-value="${unit || ''}"]`)?.click();
                    select.querySelector('[data-dl2-dd-toggle]').disabled = !!unit;   // Ubah: satuannya tetap, hanya faktor yang berubah
                    layer.classList.add('is-on');
                };
                document.addEventListener('click', e => {
                    const a = e.target.closest('a[href^="#ubah-"]');
                    if (!a) return;
                    e.preventDefault();
                    open(a.getAttribute('href').slice(6));
                });
                document.querySelector('[data-dl2-toggle="modal" data-dl2-target="#pack-unit"]').addEventListener('click', () => { select.querySelector('[data-dl2-dd-toggle]').disabled = false; used.hidden = true; factor.value = ''; err.hidden = true; });
                layer.querySelector('[data-pack-save]').addEventListener('click', async () => {
                    err.hidden = true;
                    const body = {unit: select.querySelector('[name="pack_unit"]').value, factor: parseFloat(factor.value.includes(',') ? factor.value.replace(/\./g, '').replace(',', '.') : factor.value)};
                    if (!body.unit || !(body.factor > 0)) { err.textContent = 'Pilih satuan dan isi faktor dengan angka lebih dari 0.'; err.hidden = false; return; }
                    const res = await fetch(url, {method: 'POST', headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token}, body: JSON.stringify(body)});
                    if (!res.ok) { const d = await res.json(); err.textContent = d.errors ? Object.values(d.errors)[0][0] : (d.message || 'Gagal menyimpan.'); err.hidden = false; return; }
                    location.reload();
                });
            });
        </script>
    @endunless
    @if ($isNew)
        <script>
            // Pratinjau kode barang: awalan kategori + urutan berikutnya (rumus yang sama dengan Item::nextCode).
            window.akReady(() => {
                const next = @json($nextCodes);
                const cat = document.querySelector('[name="category_id"]'), code = document.querySelector('[name="code"]');
                const show = () => { code.value = next[cat.value] || ''; };
                cat.addEventListener('change', show);
                show();
            });
        </script>
    @endif
@endsection
