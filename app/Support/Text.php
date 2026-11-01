<?php

namespace App\Support;

/* REMARK
 * Tujuan       : Teks aman dari masukan yang bisa berupa apa saja (isian bisa dikirim sebagai array, mis. name[]=x): bukan teks / angka = '' sehingga validasi "wajib diisi" yang menolaknya (422), bukan galat 500 "Array to string conversion".
 * Dipakai oleh : Form Request dengan prepareForValidation, pengendali yang membaca isian bebas (q, note, _reason, month).
 * Status       : Aktif.
 */
final class Text
{
    public static function of(mixed $v): string
    {
        return is_scalar($v) ? trim((string) $v) : '';
    }
}
