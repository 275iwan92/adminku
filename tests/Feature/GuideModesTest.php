<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\GuideCatalog;
use App\Support\Modules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga tiga mode baru Panduan Pengguna (Presentasi bertingkat, Interaktif, Blog) yang bersumber dari resources/help/guide.php:
 *                data katalog utuh (modul, gambar, titik, anchor Detail, diagram valid), disaring menurut hak akses, dan ketiga mode dirender untuk tiap tur yang tampil.
 * Dipakai oleh : php artisan test --filter=GuideModesTest.
 * Status       : Aktif.
 */
class GuideModesTest extends TestCase
{
    use RefreshDatabase;

    private function raw(): array
    {
        return require resource_path('help/guide.php');
    }

    public function test_catalog_data_is_consistent(): void
    {
        $raw = $this->raw();
        $codes = array_keys(Modules::all());
        $detail = $this->actingAs(User::factory()->administrator()->create())->get(route('help.user-guide'))->assertOk()->getContent();

        foreach ($raw['tours'] as $id => [$group, $title, $summary, $need, $shot, $anchor, $diagram, $flow, $steps, $tips, $related]) {
            $this->assertArrayHasKey($group, $raw['groups'], "Tur {$id}: kelompok tidak ada");
            foreach ($need as $c) {
                $this->assertContains($c, $codes, "Tur {$id}: kode modul {$c} tidak ada di Modules");
            }
            $this->assertFileExists(public_path("media/ug/{$shot}.png"), "Tur {$id}: gambar {$shot}");
            $this->assertStringContainsString('id="'.$anchor.'"', $detail, "Tur {$id}: anchor Detail #{$anchor} tidak ada");
            if ($diagram !== null) {
                $this->assertStringStartsWith('ug-', $diagram, "Tur {$id}: diagram TG hanya untuk Administrator");
                $this->assertFileExists(resource_path("help/diagrams/{$diagram}.svg"));
            }
            $this->assertGreaterThanOrEqual(3, count($flow), "Tur {$id}: alur kurang dari 3");
            $this->assertGreaterThanOrEqual(3, count($steps), "Tur {$id}: langkah kurang dari 3");
            $this->assertLessThanOrEqual(7, count($steps), "Tur {$id}: langkah lebih dari 7");
            foreach ($steps as [$x, $y, $t, $d]) {
                $this->assertTrue($x >= 0 && $x <= 100 && $y >= 0 && $y <= 100, "Tur {$id}: titik {$t} di luar gambar");
                $this->assertNotSame('', trim($t.$d));
            }
            foreach ($related as $r) {
                $this->assertArrayHasKey($r, $raw['tours'], "Tur {$id}: terkait {$r} tidak ada");
            }
        }
    }

    public function test_catalog_is_filtered_by_access(): void
    {
        $entry = GuideCatalog::forUser(User::factory()->create());
        $admin = GuideCatalog::forUser(User::factory()->administrator()->create());

        $this->assertArrayHasKey('grid', $entry['tours']);
        $this->assertArrayNotHasKey('pengguna', $entry['tours'], 'Data Entry tidak boleh melihat tur Pengguna');
        $this->assertArrayNotHasKey('sec', $entry['groups']);
        $this->assertArrayHasKey('pengguna', $admin['tours']);
        foreach ($entry['tours'] as $t) {
            foreach ($t['related'] as $r) {
                $this->assertArrayHasKey($r, $entry['tours'], 'Tautan terkait harus ke tur yang tampil');
            }
        }
    }

    public function test_all_three_modes_render_every_visible_tour(): void
    {
        $user = User::factory()->administrator()->create();
        $html = $this->actingAs($user)->get(route('help.user-guide'))->assertOk()->getContent();

        foreach (['deck', 'tour', 'blog', 'detail'] as $m) {
            $this->assertStringContainsString('data-mode="'.$m.'"', $html);
        }
        foreach (GuideCatalog::forUser($user)['tours'] as $id => $t) {
            $this->assertStringContainsString('data-set="t-'.$id.'"', $html, "Presentasi {$id}");
            $this->assertStringContainsString('data-stage="'.$id.'"', $html, "Interaktif {$id}");
            $this->assertStringContainsString('id="gb-'.$id.'"', $html, "Blog {$id}");
            $this->assertSame(count($t['steps']), substr_count($html, 'id="gb-'.$id.'--s'), "Langkah Blog {$id}");
        }
        $this->assertStringContainsString('id="ugf"', $html, 'Bantuan layar penuh');
        $this->assertStringContainsString('data-ugf-close', $html, 'Tombol Tutup layar penuh');
        $this->assertStringContainsString('class="g-close" href="'.route('home').'"', $html, 'Tombol Tutup di /help menuju Beranda');
        $this->assertStringContainsString(route('home').'?bantuan=', $html, '/help tanpa shell dialihkan ke Beranda yang membuka Bantuan di shell');
        $this->assertStringContainsString('prefers-reduced-motion', file_get_contents(resource_path('css/ug.css')));
        $this->assertStringContainsString('orientation: portrait', file_get_contents(resource_path('css/ug.css')), 'Putar otomatis ke landscape di HP');
    }

    public function test_entry_user_does_not_get_restricted_content_in_any_mode(): void
    {
        $html = $this->actingAs(User::factory()->create())->get(route('help.user-guide'))->assertOk()->getContent();

        $this->assertStringContainsString('id="gb-grid"', $html);
        foreach (['pengguna'] as $id) {
            $this->assertStringNotContainsString('data-set="t-'.$id.'"', $html);
            $this->assertStringNotContainsString('data-stage="'.$id.'"', $html);
            $this->assertStringNotContainsString('id="gb-'.$id.'"', $html);
        }
    }
}
