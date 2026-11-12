{{-- REMARK
 Tujuan       : Peringatan barang yang sudah kedaluwarsa atau hampir kedaluwarsa (stok masih ada): kartu alert + tabel ringkas di dashboard, tanpa popup.
 Sumber hulu  : HomeController ($expiring = Batch::nearExpiry, urut tanggal kedaluwarsa).
 Input        : expiring.
 Output       : x-dl2::alert (merah bila ada yang sudah kedaluwarsa, kuning bila hanya hampir), tabel, tombol ke Laporan Kedaluwarsa.
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
    <x-alert-bar :tone="$expired ? 'danger' : 'warn'" :message="$text" icon="fa-hourglass-end">
        <x-dl2::button size="sm" icon="fa-hourglass-end" label="Expiry Report" href="#" data-open-tab="R-EXP" />
    </x-alert-bar>
    {{-- Tabel ditulis langsung (bukan x-dl2::table) karena kolom status memakai x-dl2::badge berwarna. --}}
    <div class="dl2-table-responsive sstock-reorder">
        <table class="dl2-table is-striped">
            <thead><tr>@foreach (['Item', 'Expiry Date', 'Current Stock'] as $c)<th>{{ \Devlib2\Lang::text($c) }}</th>@endforeach</tr></thead>
            <tbody>
                @foreach ($expiring as $i)
                    @php $left = today()->diffInDays($i->expiry_date, false); @endphp
                    <tr>
                        <td>{{ $i->name }}<br><small>{{ $i->code }}</small></td>
                        <td>
                            {{ \Devlib2\Locale::d($i->expiry_date) }}<br>
                            @if ($i->expiryState() === 'KEDALUWARSA')
                                <x-dl2::badge tone="danger" icon="fa-triangle-exclamation" :label="'Lewat '.abs($left).' hari'" />
                            @else
                                <x-dl2::badge tone="warn" icon="fa-hourglass-half" :label="$left ? 'Sisa '.$left.' hari' : 'Hari ini'" />
                            @endif
                        </td>
                        <td>{{ \Devlib2\Locale::number($i->stock_qty, 2) }} {{ $i->unit }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
