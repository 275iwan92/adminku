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
        $hide = array_merge(['hold', 'email', 'print', 'new', 'undo', 'duplicate'], $me->isSupervisor() ? [] : ['cancel']);
        $n = fn ($x) => \Devlib\Locale::number($x, floor((float) $x) == (float) $x ? 0 : 3);
    @endphp
    @include('partials.flash')
    @error('lines')
        <x-dl::alert tone="danger" :message="raw($message)" />
    @enderror
    @if ($pv->exists)
        <p class="dl-note">
            <x-dl::button class="sstock-btn-amber" variant="secondary" size="sm" icon="fa-file-invoice" label="Buka PO" :href="route('po.show', $pv->order)" />
            @if ($pv->stockDocument && $me->canAccess('RCV'))
                <x-dl::button class="sstock-btn-teal" variant="secondary" size="sm" icon="fa-truck-ramp-box" label="Buka RCV di Stok" :href="route('docs.show', ['receiving', $pv->stockDocument])" />
            @endif
        </p>
    @endif
    @if ($now === 'cancelled' && $pv->exists)
        <x-dl::alert tone="warn" :message="raw('PV dibatalkan'.($pv->reason ? '. Alasan: '.$pv->reason : '').'. Stok dikembalikan dan jumlah diterima PO kembali.')" />
    @endif
    <x-dl::form :title="'Penerimaan Pembelian · '.($isNew ? 'Baru' : raw($pv->doc_no))" :subtitle="$isNew ? null : raw($pv->supplier?->name)" panel="top" kind="document" :status="$now"
        :back="route('pv.index')" :new-url="route('pv.create')" :log="$log"
        :action="$isNew ? route('pv.store') : route('pv.update', $pv)" :hidden="$isNew ? [] : ['_method' => 'PUT']"
        :pipeline="true" :hide="$hide"
        :created-by="$pv->creator?->name" :created-at="$pv->created_at?->format('Y-m-d H:i:s')" :updated-at="$pv->updated_at?->format('Y-m-d H:i:s')">
        <x-dl::form.row :cols="3">
            <x-dl::field.display label="Document No." :value="raw($pv->doc_no ?? '(otomatis)')" />
            <x-dl::field.date name="doc_date" label="Tanggal terima" :value="$d('doc_date')" required :error="$e('doc_date')" />
            @if ($isNew)
                <x-dl::field.select name="order_id" label="Pesanan Pembelian (PO)" :options="['' => 'Pilih PO...'] + $orders" :value="$order?->id" required :error="$e('order_id')" hint="Hanya PO Disetujui atau Diterima sebagian." />
            @else
                <x-dl::field.display label="Pesanan Pembelian (PO)" :value="raw($pv->order->doc_no)" />
            @endif
        </x-dl::form.row>
        <x-dl::form.row :cols="3">
            <x-dl::field.display label="Supplier" :value="raw($order?->supplier?->name ?? '(dari PO)')" />
            <x-dl::field.text name="ref_no" label="No surat jalan pemasok" :value="$v('ref_no')" maxlength="60" :error="$e('ref_no')" />
            <x-dl::field.text name="receiver" label="Penerima barang" :value="$v('receiver') ?? ($isNew ? $me->name : null)" maxlength="100" :error="$e('receiver')" />
        </x-dl::form.row>
        <x-dl::form.row :cols="3">
            <x-dl::field.text name="note" label="Catatan" :value="$v('note')" maxlength="255" :error="$e('note')" />
            @if ($pv->stockDocument)
                <x-dl::field.display label="No. RCV (Stok)" :value="raw($pv->stockDocument->doc_no)" />
            @endif
        </x-dl::form.row>
        <x-slot:detail>
            @if (! $order)
                <p class="dl-note">Pilih PO di atas; halaman memuat barang pesanannya.</p>
            @else
                <table class="dl-table" style="width:100%">
                    <thead><tr><th>Barang</th><th style="text-align:right">Dipesan</th><th style="text-align:right">Sudah diterima</th><th style="text-align:right">Sisa</th><th style="text-align:right">Terima sekarang</th><th>Sat</th><th style="text-align:right">Harga (dari PO)</th><th>Kedaluwarsa</th></tr></thead>
                    <tbody>
                    @foreach ($rows as $r)
                        <tr>
                            <td>{{ $r['label'] }}@unless ($r['master']) <span class="dl-note" title="Belum ada di master Barang: tidak bisa diterima">(belum di master)</span>@endunless</td>
                            <td style="text-align:right">{{ $n($r['ordered']) }}</td>
                            <td style="text-align:right">{{ $n($r['got']) }}</td>
                            <td style="text-align:right">{{ $n($r['left']) }}</td>
                            <td style="text-align:right"><input type="number" step="any" min="0" class="dl-input" style="width:110px;text-align:right" name="lines[{{ $r['id'] }}][qty]" value="{{ $r['qty'] }}" aria-label="Terima {{ $r['label'] }}" @disabled(! $editable || $r['left'] <= 0 && ! $pv->exists)></td>
                            <td>{{ $r['unit'] }}</td>
                            <td style="text-align:right">{{ \Devlib\Locale::number($r['price'], 0) }}</td>
                            <td>
                                @if ($r['tracks'])
                                    <input type="date" class="dl-input" name="lines[{{ $r['id'] }}][expiry_date]" value="{{ $r['expiry'] }}" aria-label="Kedaluwarsa {{ $r['label'] }}" @disabled(! $editable)>
                                @else
                                    <span class="dl-note">tidak wajib</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                <p class="dl-note">Jumlah awal = sisa PO. Ubah bila yang datang kurang; kosong atau 0 = barang itu belum datang. Konfirmasi membuat Penerimaan Barang (RCV) di Stok secara otomatis.</p>
            @endif
        </x-slot:detail>
    </x-dl::form>
    @if ($isNew)
        <script>
            document.querySelector('[name=order_id]')?.addEventListener('change', e => {
                location.href = @json(route('pv.create')) + (e.target.value ? '?po=' + encodeURIComponent(e.target.value) : '');
            });
        </script>
    @endif
@endsection
