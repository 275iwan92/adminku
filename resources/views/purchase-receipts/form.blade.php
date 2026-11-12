{{-- REMARK
 Tujuan       : Form Penerimaan Pembelian (PV): kepala (tanggal terima, PO, pemasok dari PO, surat jalan, penerima, catatan, No. RCV di Stok) + tabel barang PO: Dipesan, Sudah diterima, Sisa, Terima sekarang (isian), Sat, Harga (dari PO), Kedaluwarsa (isian bila barangnya melacak kedaluwarsa).
                Siklus tombol DevLib: Draft (Save, Confirm = RCV otomatis, Delete) > Confirmed (Cancel hanya Supervisor) | Cancelled. Memilih PO di form baru memuat barisnya dengan jumlah = sisa.
 Sumber hulu  : PurchaseReceiptController (pv, order, rows, log, orders).
 Input        : pv, order, rows (id baris PO, label, ordered, got, left, unit, price, tracks, master, qty, expiry), log, orders.
 Output       : POST pv.store (baru = draf) / PUT pv.update (save, confirm, cancel, delete); isian lines[{id baris PO}][qty|expiry_date].
 Dipakai oleh : routes pv.create, pv.show.
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Penerimaan Pembelian'])
@section('body')
    @php
        $isNew = ! $pv->exists;
        $me = auth()->user();
        $now = $isNew ? 'new' : $pv->statusNow();
        $v = fn (string $k) => old($k, $pv->{$k});
        $d = fn (string $k) => ($x = old($k, $pv->{$k})) ? \Illuminate\Support\Carbon::parse($x)->format('Y-m-d') : null;
        $e = fn (string $k) => $errors->first($k) ?: null;
        $editable = $isNew || $now === 'draft';
        $hide = array_merge(['hold', 'email', 'new', 'undo', 'duplicate'], $me->isSupervisor() ? [] : ['cancel'], $now === 'confirmed' ? [] : ['print']);
        $n = fn ($x) => \Devlib2\Locale::number($x, floor((float) $x) == (float) $x ? 0 : 3);
    @endphp
    @include('partials.flash')
    @error('lines')
        <x-dl2::alert tone="danger" :message="raw($message)" />
    @enderror
    @if ($pv->exists)
        <p class="ak-note">
            <x-dl2::button class="sstock-btn-amber" variant="secondary" size="sm" icon="fa-file-invoice" label="Buka PO" :href="route('po.show', $pv->order)" />
            @if ($pv->stockDocument && $me->canAccess('RCV'))
                <x-dl2::button class="sstock-btn-teal" variant="secondary" size="sm" icon="fa-truck-ramp-box" label="Buka RCV di Stok" :href="route('docs.show', ['receiving', $pv->stockDocument])" />
            @endif
        </p>
    @endif
    @if ($now === 'cancelled' && $pv->exists)
        <x-dl2::alert tone="warn" :message="raw('PV dibatalkan'.($pv->reason ? '. Alasan: '.$pv->reason : '').'. Stok dikembalikan dan jumlah diterima PO kembali.')" />
    @endif
    @if ($editable && $order)
        {{-- Pratinjau scan barang datang: pemindai keyboard (ketik kode + Enter) di laptop; tombol kamera hanya di HP (https). Kode dicocokkan ke kode barang baris PO. --}}
        <div class="sstock-scan" data-pv-scan>
            <x-dl2::scan name="code" label="Scan barang datang" placeholder="Scan barcode / kode barang, lalu Enter" :autofocus="false"
                hint="Tiap scan menambah 1 ke Terima sekarang pada barang yang cocok. Kamera hanya tampil di HP (butuh https)." />
            <p class="ak-note" data-scan-msg aria-live="polite"></p>
        </div>
    @endif
    <x-dl2::form :title="'Penerimaan Pembelian · '.($isNew ? 'Baru' : raw($pv->number()))" :subtitle="$isNew ? null : raw($pv->supplier?->name)" panel="top" kind="document" :status="$now"
        :back="route('pv.index')" :new-url="route('pv.create')" :log="$log"
        :action="$isNew ? route('pv.store') : route('pv.update', $pv)" :method="$isNew ? 'post' : 'put'"
        :pipeline="true" :hide="$hide" :print-url="$now === 'confirmed' ? route('pv.print', $pv) : null"
        :created-by="$pv->creator?->name" :created-at="$pv->created_at?->format('Y-m-d H:i:s')" :updated-at="$pv->updated_at?->format('Y-m-d H:i:s')">
        <x-dl2::form.row :cols="3">
            <x-dl2::field.display label="Document No." :value="raw($pv->doc_no ?? '(otomatis)')" />
            <x-dl2::field.date name="doc_date" label="Tanggal terima" :value="$d('doc_date')" required :error="$e('doc_date')" />
            @if ($isNew)
                <x-dl2::field.select name="order_id" label="Pesanan Pembelian (PO)" :options="['' => 'Pilih PO...'] + $orders" :value="$order?->id" required :error="$e('order_id')" hint="Hanya PO Disetujui atau Diterima sebagian." />
            @else
                <x-dl2::field.display label="Pesanan Pembelian (PO)" :value="raw($pv->order->doc_no)" />
            @endif
        </x-dl2::form.row>
        <x-dl2::form.row :cols="3">
            <x-dl2::field.display label="Supplier" :value="raw($order?->supplier?->name ?? '(dari PO)')" />
            <x-dl2::field.text name="ref_no" label="No surat jalan pemasok" :value="$v('ref_no')" maxlength="60" :error="$e('ref_no')" />
            <x-dl2::field.text name="receiver" label="Penerima barang" :value="$v('receiver') ?? ($isNew ? $me->name : null)" maxlength="100" :error="$e('receiver')" />
        </x-dl2::form.row>
        <x-dl2::form.row :cols="3">
            <x-dl2::field.text name="note" label="Catatan" :value="$v('note')" maxlength="255" :error="$e('note')" />
            @if ($pv->stockDocument)
                <x-dl2::field.display label="No. RCV (Stok)" :value="raw($pv->stockDocument->doc_no)" />
            @endif
        </x-dl2::form.row>
        <x-slot:detail>
            @if (! $order)
                <p class="ak-note">Pilih PO di atas; halaman memuat barang pesanannya.</p>
            @else
                <table class="dl2-table" style="width:100%">
                    <thead><tr><th>Barang</th><th style="text-align:right">Dipesan</th><th style="text-align:right">Sudah diterima</th><th style="text-align:right">Sisa</th><th style="text-align:right">Terima sekarang</th><th>Sat</th><th style="text-align:right">Harga (dari PO)</th><th>Kedaluwarsa</th></tr></thead>
                    <tbody>
                    @foreach ($rows as $r)
                        <tr data-code="{{ $r['code'] }}" data-line="{{ $r['id'] }}">
                            <td>{{ $r['label'] }}@unless ($r['master']) <span class="ak-note" title="Belum ada di master Barang: tidak bisa diterima">(belum di master)</span>@endunless</td>
                            <td style="text-align:right">{{ $n($r['ordered']) }}</td>
                            <td style="text-align:right">{{ $n($r['got']) }}</td>
                            <td style="text-align:right">{{ $n($r['left']) }}</td>
                            <td style="text-align:right"><input type="number" step="any" min="0" class="dl2-input" style="width:110px;text-align:right" name="lines[{{ $r['id'] }}][qty]" value="{{ $r['qty'] }}" aria-label="Terima {{ $r['label'] }}" @disabled(! $editable || $r['left'] <= 0 && ! $pv->exists)></td>
                            <td>{{ $r['unit'] }}</td>
                            <td style="text-align:right">{{ \Devlib2\Locale::number($r['price'], 0) }}</td>
                            <td>
                                @if ($r['tracks'])
                                    <input type="date" class="dl2-input" name="lines[{{ $r['id'] }}][expiry_date]" value="{{ $r['expiry'] }}" aria-label="Kedaluwarsa {{ $r['label'] }}" @disabled(! $editable)>
                                @else
                                    <span class="ak-note">tidak wajib</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                <p class="ak-note">Jumlah awal = sisa PO. Ubah bila yang datang kurang; kosong atau 0 = barang itu belum datang. Konfirmasi membuat Penerimaan Barang (RCV) di Stok secara otomatis.</p>
            @endif
        </x-slot:detail>
    </x-dl2::form>
    @if ($editable && $order)
        <script>
            window.akReady(() => {
                const box = document.querySelector('[data-pv-scan]'), msg = box.querySelector('[data-scan-msg]'), seen = {};
                box.querySelector('form').addEventListener('dl:scan', e => {
                    const code = e.detail.code.toUpperCase();
                    const tr = [...document.querySelectorAll('tr[data-code]')].find(r => r.dataset.code && r.dataset.code.toUpperCase() === code);
                    const input = box.querySelector('[data-scan-input]');
                    input.value = ''; input.focus();
                    if (!tr) { msg.textContent = 'Kode ' + code + ' tidak ada di PO ini.'; msg.style.color = 'var(--dl2-danger,#a32d2d)'; return; }
                    const q = tr.querySelector('input[name$="[qty]"]');
                    if (!q || q.disabled) { msg.textContent = 'Barang ' + code + ' sudah diterima penuh.'; return; }
                    seen[code] = (seen[code] || 0) + 1;   // scan pertama menimpa isian awal (= sisa PO)
                    q.value = seen[code];
                    tr.scrollIntoView({block: 'center', behavior: 'smooth'}); tr.style.outline = '2px solid var(--dl2-primary)'; setTimeout(() => { tr.style.outline = ''; }, 900);
                    msg.textContent = code + ' · terima ' + seen[code]; msg.style.color = '';
                });
            });
        </script>
    @endif
    @if ($isNew)
        <script>
            window.akReady(() => document.querySelector('[name=order_id]')?.addEventListener('change', e => {
                location.href = @json(route('pv.create')) + (e.target.value ? '?po=' + encodeURIComponent(e.target.value) : '');
            }));
        </script>
    @endif
@endsection
