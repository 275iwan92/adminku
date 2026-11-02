<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserGroup;
use App\Support\Modules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga HELP: User's Guide (Indonesia) terbuka untuk semua yang login dan hanya menampilkan modul yang boleh dibuka pengguna itu;
 *                Technical Guide (English) hanya Administrator (403 untuk URL langsung, tautan tab tidak dirender untuk peran lain); item Help ada di menu semua peran.
 *                Daftar modul (Modules::all) dicocokkan dengan anchor bantuan: modul baru wajib punya bagian bantuan. Tiap bagian modul memakai pola tetap, tiap tautan daftar isi menuju anchor yang ada,
 *                glosarium memuat istilah kunci, dan tiap diagram di resources/help/diagrams terpasang.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class HelpTest extends TestCase
{
    use RefreshDatabase;

    // Kode modul => anchor bagian bantuannya di Panduan Pengguna.
    private const ANCHORS = [
        'ITM' => 'm-itm', 'SUP' => 'm-sup', 'DEP' => 'm-dep', 'RCP' => 'm-rcp', 'UOM' => 'm-uom', 'CAT' => 'm-cat', 'LOC' => 'm-loc', 'CMP' => 'm-cmp',
        'OPN' => 't-opn', 'RCV' => 't-rcv', 'ISS' => 't-iss', 'ADJ' => 't-adj', 'SO' => 't-so', 'ORD' => 't-ord',
        'STB' => 'r-stb', 'R-CARD' => 'r-card', 'R-SUM' => 'r-sum', 'R-IN' => 'r-in', 'R-OUT' => 'r-out', 'R-EXP' => 'r-exp', 'R-ADJ' => 'r-adj',
        'GVM' => 'ga-gvm', 'GVD' => 'ga-gvd', 'GVS' => 'ga-gvs', 'GVO' => 'ga-gvo', 'GVK' => 'ga-gvk', 'GVT' => 'ga-gvt',
        'GAM' => 'ga-gam', 'GAS' => 'ga-gas', 'GAV' => 'ga-gav', 'GAK' => 'ga-gak', 'GAA' => 'ga-gaa', 'GAR' => 'ga-gar', 'GAE' => 'ga-gae', 'GAP' => 'ga-gap',
        'USR' => 'sec-usr', 'GRP' => 'sec-grp', 'SMP' => 'sec-smp', 'MOD' => 'sec-mod',
    ];

    private function guide(User $user, ?string $route = null): string
    {
        return $this->actingAs($user)->get(route($route ?? 'help.user-guide'))->assertOk()->getContent();
    }

    /** @return array{0: array<int, string>, 1: array<int, string>} id dan tautan #anchor di dalam halaman bantuan */
    private function anchors(string $html): array
    {
        $html = substr($html, (int) strpos($html, 'class="sstock-guide"'));
        preg_match_all('/\sid="([^"]+)"/', $html, $ids);
        preg_match_all('/href="#([^"]+)"/', $html, $links);

        return [$ids[1], $links[1]];
    }

    private function section(string $html, string $id): string
    {
        $from = strpos($html, 'id="'.$id.'"');
        $this->assertNotFalse($from, "anchor {$id} tidak ada");
        preg_match('/<h[23]\b/', $html, $m, PREG_OFFSET_CAPTURE, $from + 10);

        return substr($html, $from, isset($m[0]) ? $m[0][1] - $from : null);
    }

    public function test_every_module_has_a_help_section_and_a_toc_link(): void
    {
        $this->assertEqualsCanonicalizing(array_keys(Modules::all()), array_keys(self::ANCHORS), 'Modul baru wajib punya bagian bantuan: tambahkan anchor-nya di HelpTest::ANCHORS dan di help/ug.');

        $html = $this->guide(User::factory()->administrator()->create());
        foreach (self::ANCHORS as $anchor) {
            $this->assertStringContainsString('id="'.$anchor.'"', $html);
            $this->assertStringContainsString('href="#'.$anchor.'"', $html);
        }
    }

    public function test_module_sections_follow_the_role_access_exactly(): void
    {
        $group = UserGroup::create(['code' => 'GD', 'name' => 'Gudang']);
        $group->syncAccess(['ISS' => 'rw']);

        foreach ([User::factory()->create(), User::factory()->supervisor()->create(), User::factory()->administrator()->create(), User::factory()->create(['group_id' => $group->id])] as $user) {
            $html = $this->guide($user);
            foreach (self::ANCHORS as $code => $anchor) {
                $user->canAccess($code) ? $this->assertStringContainsString('id="'.$anchor.'"', $html, "{$user->role} harus melihat {$anchor}") : $this->assertStringNotContainsString('id="'.$anchor.'"', $html, "{$user->role} tidak boleh melihat {$anchor}");
            }
        }
    }

    public function test_ga_help_shows_only_the_modules_of_the_group_and_has_no_broken_links(): void
    {
        $group = UserGroup::create(['code' => 'GAO', 'name' => 'Petugas GA']);
        $group->syncAccess(['GVO' => 'rw', 'GAV' => 'ro']);
        $driver = User::factory()->create(['group_id' => $group->id]);

        $html = $this->guide($driver);
        foreach (['GVO' => true, 'GAV' => true, 'GVD' => false, 'GAP' => false, 'GAS' => false] as $code => $shown) {
            $shown ? $this->assertStringContainsString('id="'.self::ANCHORS[$code].'"', $html) : $this->assertStringNotContainsString('id="'.self::ANCHORS[$code].'"', $html);
        }
        [$ids, $links] = $this->anchors($html);
        $this->assertSame([], array_values(array_diff($links, $ids)), 'Tautan GA patah untuk pengguna dengan hak sebagian');

        $this->assertStringNotContainsString('id="ga"', $this->guide(User::factory()->create()), 'GA tidak otomatis mengikuti peran');

        // Pengaturan GA butuh peran Supervisor ke atas: Grup yang mencentangnya tidak membukanya bagi Data Entry.
        $settings = UserGroup::create(['code' => 'GAS2', 'name' => 'Pengatur GA']);
        $settings->syncAccess(['GAP' => 'rw', 'GAS' => 'rw']);
        $this->assertStringContainsString('id="ga-gap"', $this->guide(User::factory()->supervisor()->create(['group_id' => $settings->id])));
        $entry = $this->guide(User::factory()->create(['group_id' => $settings->id]));
        $this->assertStringNotContainsString('id="ga-gap"', $entry);
        $this->assertStringContainsString('id="ga-gas"', $entry);

        $admin = $this->guide(User::factory()->administrator()->create());
        foreach (['ug-ga-perpanjang-servis', 'ug-ga-kunjungan-ac'] as $diagram) {
            $this->assertStringContainsString('id="'.$diagram.'-t"', $admin);
        }
        foreach (['Aman, Segera, dan Terlambat', 'Perawatan Gedung', 'Susun otomatis', 'Kode aset versus kode ruang', 'Tandai dikonfirmasi vendor', 'Cetak Berita Acara'] as $text) {
            $this->assertStringContainsString($text, $admin, "UG GA tidak memuat {$text}");
        }
    }

    public function test_technical_guide_covers_the_ga_modules(): void
    {
        $tg = $this->guide(User::factory()->administrator()->create(), 'help.technical-guide');

        foreach (['tg-ga-erd', 'tg-ga-ac-visit-states', 'tg-ga-ac-schedule-flow'] as $diagram) {
            $this->assertStringContainsString('id="'.$diagram.'-t"', $tg);
        }
        foreach (['m_vehicle', 'm_vehicle_type_plan', 'g_vehicle_doc', 'g_vehicle_service_line', 'g_vehicle_odometer', 'm_ac_area', 'm_ac_room', 'm_ac_unit', 'm_ac_vendor', 'g_ac_service_order', 'g_ac_service_line', 'g_ac_service_reschedule',
            'VehicleCare', 'AcCare', 'AcVisits', 'AcMessage', 'AcVisitNotice', 'AcVisitPrint', 'lockForUpdate', 'ga.vehicle.soon_days', 'ga.ac.capacity', 'wa.me', 'storage/app/ga', 'give_vehicle_care_to_admin_groups', 'give_ga_settings_to_admin_groups', 'm_ac_vendor'] as $needle) {
            $this->assertStringContainsString($needle, $tg, "TG tidak memuat {$needle}");
        }
        foreach (Modules::all() as $code => $def) {
            $def[0] === 'GA' && $this->assertStringContainsString($code, $tg, "TG tidak menyebut modul {$code}");
        }
    }

    public function test_every_toc_and_inline_link_points_to_an_existing_unique_anchor(): void
    {
        $group = UserGroup::create(['code' => 'GD', 'name' => 'Gudang']);
        $group->syncAccess(['ISS' => 'rw']);

        foreach ([User::factory()->create(), User::factory()->supervisor()->create(), User::factory()->administrator()->create(), User::factory()->create(['group_id' => $group->id])] as $user) {
            [$ids, $links] = $this->anchors($this->guide($user));
            $this->assertSame([], array_values(array_diff($links, $ids)), 'Tautan ke anchor yang tidak ada');
            $this->assertSame([], array_keys(array_filter(array_count_values($ids), fn ($n) => $n > 1)), 'Anchor ganda');
        }

        [$ids, $links] = $this->anchors($this->guide(User::factory()->administrator()->create(), 'help.technical-guide'));
        $this->assertSame([], array_values(array_diff($links, $ids)), 'Tautan Technical Guide ke anchor yang tidak ada');
    }

    public function test_every_module_section_uses_the_fixed_pattern(): void
    {
        $html = $this->guide(User::factory()->administrator()->create());

        foreach ([...array_values(self::ANCHORS), 'catat-pemesanan', 't-batal'] as $anchor) {
            $section = $this->section($html, $anchor);
            foreach (['<h4>Untuk apa</h4>', '<h4>Siapa yang bisa</h4>', '<h4>Langkah', '<h4>Yang perlu diperhatikan</h4>', '<h4>Masalah umum</h4>'] as $heading) {
                $this->assertStringContainsString($heading, $section, "Bagian {$anchor} tidak memuat {$heading}");
            }
        }
    }

    public function test_start_here_roles_glossary_and_faq_are_present(): void
    {
        $html = $this->guide(User::factory()->create());
        foreach (['id="mulai"', 'id="hari-pertama"', 'id="saldo-awal-beda"', 'id="peran"', 'id="istilah"', 'id="tanya"', 'id="tombol-plus"', 'id="keluar-otomatis"', 'id="beranda"', 'id="menu"', 'id="siklus"'] as $anchor) {
            $this->assertStringContainsString($anchor, $html, $anchor);
        }
        $this->assertStringContainsString('Penerimaan pertama <b>tidak</b> otomatis menjadi Saldo Awal', $html);

        $glossary = $this->section($html, 'istilah');
        foreach (['Batch', 'FEFO', 'Saldo Awal', 'Penerimaan', 'Pengeluaran', 'Penyesuaian', 'Stok Opname', 'Selisih', 'Titik Pesan Ulang', 'Lead time', 'Satuan masuk', 'Kontan', 'Draf', 'Konfirmasi, Terkonfirmasi', 'Cut-off (titik acuan stok)', 'Stok sistem', 'Hitung buta', 'Kunci transaksi', 'Kop'] as $term) {
            $this->assertStringContainsString('<td>'.$term.'</td>', $glossary, "Istilah {$term} belum ada di glosarium");
        }

        $faq = $this->section($html, 'tanya');
        foreach (['Laporan kosong padahal', 'Saldo Awal ditolak', 'Tiba-tiba keluar', 'stok tidak cukup', 'Barang tidak muncul', 'transaksi dikunci', 'Bagaimana memakai tombol plus'] as $question) {
            $this->assertStringContainsString($question, $faq, "FAQ {$question}");
        }
    }

    public function test_kartu_stok_help_explains_opening_balance_by_from_date_and_reports_use_confirmed_documents(): void
    {
        $html = $this->guide(User::factory()->supervisor()->create());

        $this->assertStringContainsString('Membaca Saldo Awal di Kartu Stok', $html);
        $this->assertStringContainsString('baris <b>AWAL</b> tampil di daftar', $html);
        $this->assertStringContainsString('hanya memakai dokumen yang sudah <b>Terkonfirmasi</b>', $html);
        $this->assertStringContainsString('Bawaannya awal bulan ini sampai hari ini', $html);
        $this->assertStringContainsString('menampilkan Penyesuaian dengan <b>semua status</b>', $this->section($html, 'r-adj'));
        $this->assertStringNotContainsString('Konversi satuan belum ada', $html);
    }

    public function test_every_help_diagram_file_is_installed_in_its_guide(): void
    {
        $ug = $this->guide(User::factory()->administrator()->create());
        $tg = $this->get(route('help.technical-guide'))->assertOk()->getContent();

        foreach (glob(resource_path('help/diagrams/*.svg')) as $file) {
            $name = basename($file, '.svg');
            $this->assertStringContainsString('id="'.$name.'-t"', str_starts_with($name, 'ug-') ? $ug : $tg, "Diagram {$name} tidak dipasang");
            $this->assertNotFalse(simplexml_load_file($file), "{$name} bukan XML yang sah");
        }

        foreach (['ug-hari-pertama', 'ug-saldo-awal-vs-penerimaan', 'ug-kartu-stok-saldo-awal', 'ug-opname-langkah', 'ug-satuan-masuk', 'ug-keluar-otomatis', 'ug-pesan-ulang', 'ug-stok-opname'] as $name) {
            $this->assertFileExists(resource_path('help/diagrams/'.$name.'.svg'));
        }
    }

    public function test_technical_guide_covers_the_architecture_topics(): void
    {
        $tg = $this->guide(User::factory()->administrator()->create(), 'help.technical-guide');

        foreach (['lockAndAssert', 'createNumbered', 'unreversed()', 'bcmath', 'FEFO', 'm_item_unit', 'ReorderOrders::reconcile', 'Devlib\Engine\LeadTime', 'App\Support\QuickAdd', 'x-dl::field.select', 'x-dl::idle-logout', 'SESSION_LIFETIME', 'App\Support\Constants', 'stock_counts', 'StockLedger::assertNotLocked()', 'Install steps', 'id="testing"', 'composer setup', 'php artisan test'] as $needle) {
            $this->assertStringContainsString($needle, $tg, "TG tidak memuat {$needle}");
        }
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(route('help.user-guide'))->assertRedirect(route('login'));
        $this->get(route('help.technical-guide'))->assertRedirect(route('login'));
    }

    public function test_user_guide_opens_for_every_role(): void
    {
        foreach ([User::factory(), User::factory()->supervisor(), User::factory()->administrator()] as $factory) {
            $this->actingAs($factory->create())->get(route('help.user-guide'))->assertOk()->assertSee('Panduan Pengguna Stoku')->assertSee('Cara memakai Grid');
        }
    }

    public function test_user_guide_lists_only_modules_the_role_may_open(): void
    {
        $entry = $this->actingAs(User::factory()->create())->get(route('help.user-guide'))->assertOk();
        $entry->assertSee('id="t-rcv"', false)->assertSee('id="t-iss"', false)->assertSee('id="laporan"', false);
        $entry->assertDontSee('id="t-opn"', false)->assertDontSee('id="t-adj"', false)->assertDontSee('id="keamanan"', false)->assertDontSee('id="m-uom"', false);

        $sup = $this->actingAs(User::factory()->supervisor()->create())->get(route('help.user-guide'))->assertOk();
        $sup->assertSee('id="t-opn"', false)->assertSee('id="t-adj"', false)->assertSee('id="m-uom"', false)->assertDontSee('id="keamanan"', false);

        $this->actingAs(User::factory()->administrator()->create())->get(route('help.user-guide'))->assertOk()->assertSee('id="keamanan"', false);
    }

    public function test_guides_describe_incoming_units_per_item_and_the_reorder_note(): void
    {
        $this->actingAs(User::factory()->supervisor()->create())->get(route('help.user-guide'))->assertOk()
            ->assertSee('Satuan masuk (kemasan saat barang diterima)', false)->assertSee('Faktor satuan belum ada', false)
            ->assertSee('Diterima sejak dipesan', false)->assertSee('Jumlah Dipesan', false)->assertSee('bukan dokumen PO', false)->assertSee('Perkiraan tiba', false)->assertSee('Saran Titik Pesan Ulang', false)->assertSee('Lead Time Otomatis (d)', false)->assertSee('selalu dalam satuan stok barang', false)
            ->assertSee('Faktor ini sudah dipakai di dokumen; hubungi Supervisor untuk mengubahnya.', false);

        $this->actingAs(User::factory()->administrator()->create())->get(route('help.technical-guide'))->assertOk()
            ->assertSee('m_item_unit', false)->assertSee('ProductUnits::save', false)->assertSee('ReorderOrder::note()', false)->assertSee('add_supplier_qty_note_to_reorder_orders', false)->assertSee('App\Services\LeadTimes', false)->assertSee('Devlib\Engine\LeadTime', false);
    }

    public function test_only_administrators_open_the_technical_guide(): void
    {
        $this->actingAs(User::factory()->create())->get(route('help.technical-guide'))->assertForbidden();
        $this->actingAs(User::factory()->supervisor()->create())->get(route('help.technical-guide'))->assertForbidden();
        $this->actingAs(User::factory()->administrator()->create())->get(route('help.technical-guide'))->assertOk()->assertSee('Stoku Technical Guide');
    }

    public function test_technical_guide_tab_is_rendered_only_for_administrators(): void
    {
        $tg = route('help.technical-guide');

        $this->actingAs(User::factory()->create())->get(route('help.user-guide'))->assertDontSee($tg, false)->assertDontSee('>Technical Guide</a>', false);
        $this->actingAs(User::factory()->supervisor()->create())->get(route('help.user-guide'))->assertDontSee($tg, false);
        $this->actingAs(User::factory()->administrator()->create())->get(route('help.user-guide'))->assertSee($tg, false);
        $this->actingAs(User::factory()->administrator()->create())->get($tg)->assertSee(route('help.user-guide'), false);
    }

    public function test_flows_are_inline_svg_diagrams_kept_outside_public(): void
    {
        $ug = $this->actingAs(User::factory()->administrator()->create())->get(route('help.user-guide'))->assertOk();
        $tg = $this->get(route('help.technical-guide'))->assertOk();

        foreach (['ug-alur-kerja', 'ug-siklus-dokumen', 'ug-pembatalan'] as $name) {
            $ug->assertSee('id="'.$name.'-t"', false);
        }
        foreach (['tg-system-flow', 'tg-doc-lifecycle', 'tg-adjustment-lifecycle', 'tg-stock-ledger', 'tg-erd', 'tg-access-flow'] as $name) {
            $tg->assertSee('id="'.$name.'-t"', false);
        }
        $this->assertDirectoryDoesNotExist(public_path('help'));
        $this->assertFileDoesNotExist(public_path('help/diagrams/tg-erd.svg'));
    }

    public function test_help_item_is_in_the_menu_of_every_role_and_opens_the_user_guide(): void
    {
        $url = route('help.user-guide');

        foreach ([User::factory(), User::factory()->supervisor(), User::factory()->administrator()] as $factory) {
            $this->actingAs($factory->create())->get(route('home'))->assertSee('data-dl-help="'.e($url).'"', false);
        }
    }

    public function test_help_covers_session_idle_logout_and_conversion_rules(): void
    {
        $ug = $this->actingAs(User::factory()->administrator()->create())->get(route('help.user-guide'))->assertOk();
        $ug->assertSee('langsung keluar')->assertSee('Tidak ada pilihan &quot;ingat saya&quot;', false)->assertSee('Tetap masuk')->assertSee('30 menit')
            ->assertSee('hanya bisa diubah Supervisor')->assertSee('Dihitung presisi');

        $this->get(route('help.technical-guide'))->assertOk()->assertSee('x-dl::idle-logout')->assertSee('session.ping')->assertSee('SESSION_LIFETIME=30')
            ->assertSee('EnsureActive')->assertSee('bcmath')->assertSee('global');
    }

    public function test_help_covers_quick_add_and_the_kontan_supplier(): void
    {
        $ug = $this->actingAs(User::factory()->administrator()->create())->get(route('help.user-guide'))->assertOk();
        $ug->assertSee('Tombol plus (Tambah baru)')->assertSee('Kontan')->assertSee('supplier sistem')->assertDontSee('Penerima baru</b>', false);

        $this->get(route('help.technical-guide'))->assertOk()->assertSee('QuickAdd')->assertSee('is_system')->assertSee('add_system_supplier_kontan');
    }
}
