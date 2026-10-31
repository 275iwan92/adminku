{{-- REMARK
 Tujuan       : Peringatan barang yang sudah kedaluwarsa atau hampir kedaluwarsa (stok masih ada): kartu alert + tabel ringkas di dashboard, tanpa popup.
 Sumber hulu  : HomeController ($expiring = Batch::nearExpiry, urut tanggal kedaluwarsa).
 Input        : expiring.
 Output       : x-dl::alert (merah bila ada yang sudah kedaluwarsa, kuning bila hanya hampir), tabel, tombol ke Laporan Kedaluwarsa.
 Dipakai oleh : home.blade.php (slot dashboard).
 Status       : Aktif.
--}}
@if ($expiring->isNotEmpty())
    @php
        $expired = $expiring->filter(fn ($i) => $i->expiryState() === 'KEDALUWARSA')->count();
        $text = $expired
            ? $expired.' barang sudah kedaluwarsa dan '.($expiring->count() - $expired).' barang hampir kedaluwarsa (kurang dari '.\App\Models\Batch::EXPIRY_WARN_DAYS.' hari). Pisahkan dari rak dan keluarkan lewat Penyesuaian Stok.'
            : $expiring->count().' barang akan kedaluwarsa dalam '.\App\Models\Batch::EXPIRY_WARN_DAYS.' hari. Gunakan lebih dulu.';
    @endphp
    <x-dl::alert :tone="$expired ? 'danger' : 'warn'" :message="$text">
        <x-dl::button variant="secondary" size="sm" icon="fa-hourglass-end" label="Expiry Report" href="#" data-open-tab="R-EXP" />
    </x-dl::alert>
    {{-- Tabel ditulis langsung (bukan x-dl::table) karena kolom status memakai x-dl::badge berwarna. --}}
    <div class="dl-table-wrap sstock-reorder">
        <table class="dl-table is-striped">
            <thead><tr>@foreach (['Item', 'Expiry Date', 'Current Stock'] as $c)<th>{{ \Devlib\Lang::text($c) }}</th>@endforeach</tr></thead>
            <tbody>
                @foreach ($expiring as $i)
                    @php $left = today()->diffInDays($i->expiry_date, false); @endphp
                    <tr>
                        <td>{{ $i->name }}<br><small>{{ $i->code }}</small></td>
                        <td>
                            {{ \Devlib\Locale::d($i->expiry_date) }}<br>
                            @if ($i->expiryState() === 'KEDALUWARSA')
                                <x-dl::badge tone="danger" icon="fa-triangle-exclamation" :label="'Lewat '.abs($left).' hari'" />
                            @else
                                <x-dl::badge tone="warn" icon="fa-hourglass-half" :label="$left ? 'Sisa '.$left.' hari' : 'Hari ini'" />
                            @endif
                        </td>
                        <td>{{ \Devlib\Locale::number($i->stock_qty, 2) }} {{ $i->unit }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
