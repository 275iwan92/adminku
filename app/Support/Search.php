<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/* REMARK
 * Tujuan       : Pencarian barang yang toleran: beberapa kata cocok dalam urutan bebas (AND per kata, tiap kata boleh ada di kolom mana pun), tanda % dan _ yang diketik dicari apa adanya,
 *                kode tidak peduli strip / spasi / angka nol di depan ("atk0002", "atk 2", "atk2" mencocokkan ATK-0002), dan ukuran tanpa spasi ("6mm") cocok dengan "6 mm".
 * Input        : query Eloquent, teks cari, kolom teks, kolom kode, penambah syarat per kata (opsional).
 * Dipakai oleh : ItemLookups (lookup baris dokumen), HpController@find (Cari Barang HP). Grid Barang mencari di sisi klien (DevLib) dan tidak lewat sini.
 * Status       : Aktif.
 */
final class Search
{
    /** @return list<string> kata cari tanpa spasi ganda */
    public static function tokens(string $q): array
    {
        return array_values(array_filter(preg_split('/\s+/u', trim($q)) ?: [], fn ($t) => $t !== ''));
    }

    /**
     * @param  list<string>  $columns  kolom teks (nama, lokasi, ...)
     * @param  list<string>  $codes  kolom kode
     * @param  (callable(\Illuminate\Database\Eloquent\Builder, string): void)|null  $extra  syarat OR tambahan per kata, menerima builder grup dan pola LIKE
     */
    public static function apply(Builder $query, string $q, array $columns, array $codes = ['code'], ?callable $extra = null): Builder
    {
        $grammar = $query->getQuery()->getGrammar();

        foreach (self::tokens($q) as $token) {
            $like = self::like($token);
            $flat = preg_replace('/[^\p{L}\p{N}]/u', '', $token) ?? '';
            $query->where(function ($w) use ($token, $like, $flat, $columns, $codes, $extra, $grammar) {
                foreach ([...$columns, ...$codes] as $c) {
                    $col = $grammar->wrap($c);
                    $w->orWhereRaw("{$col} like ? escape '!'", [$like]);
                    if (preg_match('/\d/', $token)) {   // ukuran / tipe berangka: "6mm" juga cocok dengan "6 mm"
                        $w->orWhereRaw("replace({$col}, ' ', '') like ? escape '!'", [$like]);
                    }
                }
                foreach ($flat === '' ? [] : $codes as $c) {
                    $col = $grammar->wrap($c);
                    $w->orWhereRaw("replace(replace({$col}, '-', ''), ' ', '') like ? escape '!'", [self::like($flat)]);
                    // Angka nol di depan diabaikan: "atk2" / "atk 2" -> ATK-2, ATK-02, ATK-0002, ... (juga kode batch ATK-0002-261014).
                    if (preg_match('/^(\p{L}+)[\s-]*0*(\d+)$/u', $token, $m)) {
                        $names = array_map(fn ($width) => mb_strtoupper($m[1]).'-'.str_pad($m[2], $width, '0', STR_PAD_LEFT), range(strlen($m[2]), max(strlen($m[2]), 6)));
                        $w->orWhereIn($c, $names);
                        foreach ($names as $name) {
                            $w->orWhereRaw("{$col} like ? escape '!'", [self::escape($name).'-%']);
                        }
                    }
                }
                if ($extra) {
                    $extra($w, $like);
                }
            });
        }

        return $query;
    }

    // Pola LIKE "mengandung" dengan %, _ dan ! dari ketikan dicari apa adanya.
    public static function like(string $text): string
    {
        return '%'.self::escape($text).'%';
    }

    private static function escape(string $text): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($text));
    }
}
