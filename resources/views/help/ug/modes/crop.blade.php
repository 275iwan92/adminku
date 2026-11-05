{{-- REMARK
 Tujuan       : Ilustrasi "potongan" tangkapan layar: gambar penuh diperbesar di sekitar satu titik (x%, y%), dengan cincin penanda berdenyut. Dipakai kartu dan langkah mode Blog.
 Input        : shot, x, y, zoom (bawaan 2.2), ring (bawaan true).
 Dipakai oleh : help/ug/modes/blog.blade.php.
 Status       : Aktif.
--}}
<div class="ug-crop" aria-hidden="true"><img src="{{ asset('media/ug/'.$shot.'.png') }}" alt="" width="1440" height="900" loading="lazy" decoding="async" style="transform-origin: {{ $x }}% {{ $y }}%; --z: {{ $zoom ?? 2.2 }}">@if ($ring ?? true)<i class="ug-ring" style="left: {{ $x }}%; top: {{ $y }}%"></i>@endif</div>
