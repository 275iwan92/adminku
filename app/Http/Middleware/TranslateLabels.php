<?php

namespace App\Http\Middleware;

use Closure;
use Devlib2\Lang;
use Devlib2\Locale;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/* REMARK
 * Tujuan       : Menerjemahkan label English komponen x-dl2::* ke Indonesia memakai kamus aplikasi (Indonesian, lewat Devlib2\Lang::text). DevLib 1 menerjemahkan label di PHP; DevLib 2 belum, jadi layar yang ditulis English
 *                (kebiasaan DevLib) akan tampil English tanpa lapisan ini. Yang diterjemahkan hanya teks yang PERSIS sama dengan kunci kamus: simpul teks HTML, nilai atribut, dan nilai string di JSON konfigurasi komponen.
 * Sumber hulu  : App\Support\Indonesian (Lang::translateUsing), Devlib2\Locale::lang().
 * Input        : respons HTML (200) desktop / HP bila bahasa = id.
 * Output       : HTML yang sama dengan label Indonesia.
 * Dipakai oleh : bootstrap/app.php (grup web).
 * Status       : Aktif (hapus bila DevLib 2 menerjemahkan label komponen sendiri).
 */
class TranslateLabels
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if ($response->getStatusCode() !== 200 || Locale::lang() !== 'id' || ! str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            return $response;
        }
        $html = $response->getContent();
        if (! is_string($html) || $html === '') {
            return $response;
        }
        $cache = [];
        $tr = static function (string $raw) use (&$cache): ?string {
            if (array_key_exists($raw, $cache)) {
                return $cache[$raw];
            }
            $plain = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5);
            $key = trim($plain);
            $out = null;
            if ($key !== '' && mb_strlen($key) <= 160 && preg_match('/[A-Za-z]/', $key)) {
                $t = Lang::text($key);
                if ($t !== $key && ! str_contains($t, '"') && ! str_contains($t, '\\') && ! str_contains($t, '<')) {
                    $lead = substr($plain, 0, strlen($plain) - strlen(ltrim($plain)));
                    $trail = substr($plain, strlen(rtrim($plain)));
                    $out = $lead.htmlspecialchars($t, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8').$trail;
                }
            }

            return $cache[$raw] = $out;
        };
        $re = [
            '/(?<=>)([^<>]{2,200})(?=<)/u',                       // simpul teks
            '/(?<=&quot;)((?:[^&<>]|&(?!quot;))+?)(?=&quot;)/u',   // nilai string di JSON konfigurasi (atribut HTML)
            '/(?<=")([^"<>]{2,160})(?=")/u',                      // nilai atribut
        ];
        foreach ($re as $r) {
            $html = preg_replace_callback($r, static fn (array $m) => $tr($m[1]) ?? $m[1], $html) ?? $html;
        }
        $original = $response->original ?? null;   // setContent menimpa original (view + data) yang dipakai assertViewHas di tes
        $response->setContent($html);
        if ($original !== null) {
            $response->original = $original;
        }

        return $response;
    }
}
