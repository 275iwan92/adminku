{{-- REMARK
 Tujuan       : Peringatan item yang stoknya menyentuh / di bawah Reorder Point: kartu alert + tabel ringkas (nama, jumlah pesan ulang, stok saat ini) di dashboard, tanpa popup.
 Sumber hulu  : HomeController ($below = Item::belowReorderPoint, stok tersedia = batch belum kedaluwarsa).
 Input        : below.
 Output       : x-dl::alert, x-dl::table (termasuk status pesanan + keterangan: jumlah, supplier, tanggal, ref, oleh, diterima sejak dipesan), x-dl::button menuju Receiving, modal catat pemesanan (supplier, jumlah, tanggal, ref, catatan), modal Tutup Pesanan per pesanan terbuka (alasan opsional).
 Dipakai oleh : home.blade.php (slot dashboard).
 Status       : Aktif.
--}}
@if ($below->isNotEmpty())
    @php
        $text = $below->count().' barang berada di atau di bawah titik pesan ulang. Segera pesan ke pemasok.';
        $cols = ['Item', 'Reorder Qty', 'Available Stock', 'Order Status'];
        $canOrder = auth()->user()->canWrite('ORD');   // Catat Pemesanan / Tutup Pesanan = tulis modul ORD (server menolak yang lain dengan 403)
        $orderable = $below->filter(fn ($i) => ! $i->openOrder)->mapWithKeys(fn ($i) => [$i->id => $i->name])->all();
        $units = $below->filter(fn ($i) => ! $i->openOrder)->mapWithKeys(fn ($i) => [$i->id => $i->unit])->all();
    @endphp
    <x-alert-bar tone="danger" :message="$text">
        <x-dl::button variant="secondary" size="sm" icon="fa-truck-ramp-box" label="Open Receiving" href="#" data-open-tab="RCV" />
        @if ($orderable && $canOrder)
            <x-dl::button size="sm" icon="fa-cart-shopping" label="Record Order" data-dl-open="reorder-modal" />
        @endif
    </x-alert-bar>
    @if ($orderable && $canOrder)
        <x-dl::modal id="reorder-modal" title="Record Order" icon="fa-cart-shopping" size="sm">
            <form id="reorder-form" method="POST" action="{{ route('reorder-orders.store') }}">
                @csrf
                <x-dl::field.select name="item_id" label="Item" :options="$orderable" required />
                <x-dl::field.select name="supplier_id" label="Supplier" :options="$suppliers" required :add="\App\Support\QuickAdd::for('supplier')" />
                <x-dl::field.decimal name="qty" label="Jumlah Dipesan" :decimals="3" :value="0" required />
                <p class="dl-note" data-reorder-unit>Satuan: satuan stok barang.</p>
                <x-dl::field.date name="ordered_at" label="Order Date" :value="today()->format('Y-m-d')" required />
                <x-dl::field.text name="ref_no" label="Order Reference No." maxlength="60" />
                <x-dl::field.text name="note" label="Catatan" maxlength="255" />
            </form>
            <x-slot:footer><x-dl::button type="submit" form="reorder-form" icon="save" label="Save" /></x-slot:footer>
        </x-dl::modal>
        <script>
            // Satuan di samping jumlah mengikuti barang yang dipilih (jumlah selalu dalam satuan stok barang).
            (() => {
                // Dialog dipindah ke <body> (seperti popup tambah supplier): di dalam Layer Shell popup tambah supplier tertutup dialog ini.
                document.body.append(document.querySelector('[data-dl-modal-id="reorder-modal"]'));
                const units = @json($units), note = document.querySelector('[data-reorder-unit]'), pick = document.querySelector('#reorder-form [name="item_id"]');
                const show = () => { note.textContent = pick.value && units[pick.value] ? 'Satuan: ' + units[pick.value] + ' (satuan stok barang)' : 'Satuan: satuan stok barang.'; };
                pick.addEventListener('change', show);
                new MutationObserver(show).observe(pick, {attributes: true, attributeFilter: ['value']});
                document.querySelectorAll('#reorder-form [data-dl-select] [data-dl-dd-list]').forEach(l => l.addEventListener('click', () => setTimeout(show, 0)));
                show();
            })();
        </script>
    @endif
    {{-- Tabel ditulis langsung (bukan x-dl::table) karena kolom status memakai x-dl::badge berwarna (Dipesan / Diterima sebagian). --}}
    <div class="dl-table-wrap sstock-reorder">
        <table class="dl-table is-striped">
            <thead><tr>@foreach ($cols as $c)<th>{{ \Devlib\Lang::text($c) }}</th>@endforeach</tr></thead>
            <tbody>
                @foreach ($below as $i)
                    <tr>
                        <td>{{ $i->name }}</td>
                        <td>{{ \Devlib\Locale::number($i->reorder_qty, 2) }} {{ $i->unit }}</td>
                        <td>{{ \Devlib\Locale::number($i->available_qty, 2) }} {{ $i->unit }}</td>
                        <td>
                            @if ($i->openOrder)
                                @php $partial = $i->openOrder->status() === 'partial'; @endphp
                                <x-dl::badge :tone="$partial ? 'warn' : 'success'" :icon="$partial ? 'fa-box-open' : 'fa-circle-check'" :label="$i->openOrder->statusLabel().' '.$i->openOrder->ordered_at->format('d/m/Y').($i->openOrder->ref_no ? ' · '.$i->openOrder->ref_no : '')" />
                                @if ($canOrder)<x-dl::button variant="secondary" size="sm" icon="fa-ban" label="Close Order" :data-dl-open="'close-order-'.$i->openOrder->id" />@endif
                                <div class="dl-note">{{ $i->openOrder->note($leadTimes[$i->id] ?? null) }}</div>
                            @else
                                <x-dl::badge tone="danger" icon="fa-triangle-exclamation" label="Belum dipesan" />
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    {{-- Tutup manual: untuk pesanan yang tidak akan dipenuhi penuh (supplier mengirim kurang). Tidak menghapus apa pun; jumlah yang sudah diterima tetap tercatat di Penerimaan. --}}
    @foreach ($canOrder ? $below->filter(fn ($i) => $i->openOrder) : [] as $i)
        <x-dl::modal :id="'close-order-'.$i->openOrder->id" title="Close Order" icon="fa-ban" size="sm">
            <form id="close-order-form-{{ $i->openOrder->id }}" method="POST" action="{{ route('reorder-orders.close', $i->openOrder) }}">
                @csrf
                <p class="dl-note">Tutup pesanan {{ $i->name }} yang belum diterima penuh? Pesanan yang sudah ditutup tidak bisa dibuka lagi.</p>
                <x-dl::field.text name="note" label="Alasan (opsional)" maxlength="150" />
            </form>
            <x-slot:footer><x-dl::button type="submit" :form="'close-order-form-'.$i->openOrder->id" icon="save" label="Close Order" /></x-slot:footer>
        </x-dl::modal>
    @endforeach
@endif
