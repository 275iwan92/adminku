{{-- REMARK
 Tujuan       : Banner peringatan dashboard Stok berbaris rapi (ikon, teks, tombol aksi di kanan; membungkus di layar sempit). Pengganti x-dl2::alert untuk banner yang punya tombol (teks DevLib tidak bisa dibungkus, jadi tombol jatuh ke baris bawah).
 Input        : tone (danger|warn|info), message (teks aman/escape), icon (opsional), slot = x-dl2::button (tombol tanpa variant = aksi utama berwarna tegas, variant secondary = outline).
 Output       : div.sstock-abar (CSS di resources/css/app.css).
 Dipakai oleh : reorder-alert, expiry-alert, count-reminder.
 Status       : Aktif.
--}}
@props(['tone' => 'danger', 'message' => '', 'icon' => null])
@php $icon ??= ['danger' => 'fa-triangle-exclamation', 'warn' => 'fa-circle-exclamation', 'info' => 'fa-circle-info'][$tone] ?? 'fa-circle-info'; @endphp
<div {{ $attributes->class(['sstock-abar', 'sstock-abar--'.$tone]) }} role="alert">
    <i class="sstock-abar__i fa-solid {{ $icon }}" aria-hidden="true"></i>
    <p class="sstock-abar__t">{{ $message }}</p>
    @if (trim($slot) !== '')<div class="sstock-abar__act">{{ $slot }}</div>@endif
</div>
