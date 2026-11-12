{{-- REMARK
 Tujuan       : Latar foto untuk layar masuk tampilan HP: satu foto acak (kembaran tegak 9:16 bila ada, kalau tidak foto lanskap), kotak isian di atasnya berupa kartu permukaan (teks tetap kontras).
 Input        : slot = x-dl2::phone-login atau main.ak-plogin.
 Output       : div.sstock-hpwall; tanpa foto (folder kosong) hanya warna latar DevLib.
 Dipakai oleh : login-hp, auth/forgot-password, auth/reset-password (cabang HP).
 Status       : Aktif.
--}}
@php($photo = \App\Support\Wallpapers::random())
<div {{ $attributes->class(['sstock-hpwall', 'has-photo' => $photo]) }}>
    @if ($photo)<img class="sstock-hpwall__img" src="{{ $photo }}" alt="" decoding="async">@endif
    {{ $slot }}
</div>
