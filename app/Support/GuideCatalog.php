<?php

namespace App\Support;

use App\Models\User;

/* REMARK
 * Tujuan       : Memuat isi tiga mode Panduan Pengguna dari resources/help/guide.php dan menyaringnya menurut hak akses pengguna (tur tampil bila ia boleh membuka salah satu modul yang dibutuhkan; kelompok tampil bila punya tur).
 * Output       : forUser(User) = ['groups' => [kunci => grup + 'tours' => [id => tur]], 'tours' => [id => tur]]; setiap tur memuat kunci 'id' dan 'group'.
 * Dipakai oleh : help/user-guide.blade.php (lewat View::share di route) dan tests/Feature/GuideModesTest.
 * Status       : Aktif.
 */
final class GuideCatalog
{
    /** @return array{groups: array<string, array>, tours: array<string, array>} */
    public static function forUser(User $user): array
    {
        $raw = require resource_path('help/guide.php');
        $tours = [];
        foreach ($raw['tours'] as $id => $t) {
            [$group, $title, $summary, $need, $shot, $anchor, $diagram, $flow, $steps, $tips, $related] = $t;
            if ($need !== [] && ! collect($need)->contains(fn ($c) => $user->canAccess($c))) {
                continue;
            }
            $tours[$id] = compact('id', 'group', 'title', 'summary', 'need', 'shot', 'anchor', 'diagram', 'flow', 'steps', 'tips', 'related');
        }
        // Tautan "terkait" hanya ke tur yang tampil bagi pengguna ini.
        foreach ($tours as $id => $t) {
            $tours[$id]['related'] = array_values(array_filter($t['related'], fn ($r) => isset($tours[$r]) && $r !== $id));
        }
        $groups = [];
        foreach ($raw['groups'] as $key => [$title, $tag, $hue, $shot, $codes, $sub]) {
            $own = array_filter($tours, fn ($t) => $t['group'] === $key);
            if ($own === []) {
                continue;
            }
            $groups[$key] = compact('key', 'title', 'tag', 'hue', 'shot', 'sub') + ['tours' => $own];
        }

        return ['groups' => $groups, 'tours' => $tours];
    }
}
