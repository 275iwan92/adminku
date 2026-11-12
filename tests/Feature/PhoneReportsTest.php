<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga laporan di Tampilan HP: tiap laporan memakai phone-list DevLib (daftar kartu menggantikan lembar A4 yang diperkecil) dan tetap dapat dibuka.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class PhoneReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_report_declares_the_phone_reading_list(): void
    {
        $sup = User::factory()->supervisor()->create();
        $this->withCookie('ui', 'phone');

        foreach (['reports.summary', 'reports.expiry', 'reports.receiving', 'reports.issuance', 'reports.adjustment'] as $route) {
            $this->actingAs($sup)->get(route($route))->assertOk()->assertSee('&quot;phoneList&quot;:true', false);   // report DevLib 2: daftar kartu HP lewat opsi phoneList
        }
        $this->actingAs($sup)->get(route('reports.stock-card'))->assertOk();
    }

    public function test_desktop_reports_keep_the_scaled_sheets_without_the_phone_list_hook_being_active(): void
    {
        // Atribut ada (opt-in diaktifkan di Stoku), tetapi daftar kartu hanya dibangun dan ditampilkan di konteks phone: tubuh halaman desktop tidak berkelas dl-on-phone.
        $sup = User::factory()->supervisor()->create();
        $this->withCookie('ui', 'desktop');

        $html = $this->actingAs($sup)->get(route('reports.summary'))->assertOk()->getContent();
        $this->assertStringNotContainsString('dl-on-phone', $html);
    }
}
