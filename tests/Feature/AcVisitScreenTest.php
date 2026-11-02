<?php

namespace Tests\Feature;

use App\Mail\AcVisitNotice;
use App\Models\AcOrder;
use App\Models\User;
use App\Services\AcCare;
use App\Services\AcVisits;
use App\Support\Constants;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\AcFixtures;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga layar Kunjungan AC lewat HTTP: daftar dan statusnya, layar satu pesanan (empat tahap, pratinjau pesan vendor dan PIC dengan tautan WhatsApp), Kirim yang baru mengirim e-mail saat ditekan,
 *                urutan tahap tidak bisa dilompati dari layar, jadwal ulang dan batal dengan alasan, foto bukti opsional (jpg / png, maks 5 MB, privat), cetak Berita Acara, hak Grup, dan jumlah kueri yang tetap.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class AcVisitScreenTest extends TestCase
{
    use AcFixtures, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-02 08:00:00');
        Constants::forget();
        Storage::fake('ga');
        Mail::fake();
        $this->admin = User::factory()->administrator()->create(['name' => 'Sari GA']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Constants::forget();
        parent::tearDown();
    }

    private function order(int $units = 2, string $date = '2026-11-03'): AcOrder
    {
        return $this->acOrder($this->acVendor(), array_map(fn () => $this->acLate(), range(1, $units)), $date, $this->admin);
    }

    public function test_the_list_shows_orders_with_their_status_and_the_buttons_follow_the_stage(): void
    {
        $a = $this->order(2, '2026-11-03');
        $b = $this->acOrder($this->acVendor(), [$this->acLate()], '2026-11-04', $this->admin);
        AcVisits::send($b, $this->admin);

        $this->actingAs($this->admin)->get(route('ac-visits.index'))->assertOk()->assertSee($a->no)->assertSee($b->no)->assertSee('Terkirim')->assertSee('Draf')->assertSee('Dikonfirmasi vendor');

        $this->get(route('ac-visits.show', $a))->assertOk()->assertSee('Kunjungan · '.$a->no, false)->assertSee('1. Draf')->assertSee('2. Terkirim')->assertSee('3. Dikonfirmasi')->assertSee('4. Selesai')
            ->assertSee('Selasa, 03-11-2026')->assertSee('09.00')->assertSee('2 unit')->assertSee('Cetak Berita Acara')
            ->assertSee('Kirim')->assertDontSee('Tandai dikonfirmasi vendor')->assertDontSee('Tandai selesai');
        $this->get(route('ac-visits.show', $b))->assertOk()->assertSee('Kirim ulang')->assertSee('Tandai dikonfirmasi vendor')->assertDontSee('Tandai selesai');
    }

    public function test_the_preview_has_the_vendor_text_the_whatsapp_link_and_the_pic_copies(): void
    {
        $o = $this->order(2);
        $res = $this->actingAs($this->admin)->get(route('ac-visits.show', $o))->assertOk();

        $res->assertSee('Yth. Pak Andi,')->assertSee('Jam mulai    : 09.00', false)->assertSee('Jumlah AC    : 2 unit', false)->assertSee('Cuci rutin')->assertSee('PIC Budi');
        $res->assertSee('https://wa.me/6281234567000?text=', false)->assertSee('Buka WhatsApp');
        $res->assertSee('vendor@example.test')->assertSee('+6281234567000');
        $res->assertSee('Salinan untuk PIC area')->assertSee('02-11-2026 pukul 15.00')->assertSee('https://wa.me/6281234567001?text=', false)->assertSee('Buka WhatsApp PIC');
        Mail::assertNothingSent();   // membuka layar tidak mengirim apa pun
    }

    public function test_pressing_send_emails_once_marks_the_stage_and_points_to_whatsapp(): void
    {
        $o = $this->order();
        $this->actingAs($this->admin)->post(route('ac-visits.send', $o))->assertRedirect(route('ac-visits.show', $o))
            ->assertSessionHas('status', fn ($m) => str_contains($m, 'e-mail terkirim ke vendor@example.test') && str_contains($m, 'Buka WhatsApp'));
        Mail::assertSent(AcVisitNotice::class, 1);
        $o = $o->fresh();
        $this->assertSame(['sent', $this->admin->id], [$o->status, $o->sent_by]);
        $this->get(route('ac-visits.show', $o))->assertSee('Sari GA');   // tahap Terkirim mencatat pengirim
    }

    public function test_stages_cannot_be_skipped_from_the_screen(): void
    {
        $o = $this->order();
        $this->actingAs($this->admin);
        $this->post(route('ac-visits.confirm', $o))->assertSessionHasErrors(['status' => 'Tandai dikonfirmasi vendor tidak bisa: pesanan '.$o->no.' berstatus Draf. Urutan tahap: Draf, Terkirim, Dikonfirmasi vendor, Selesai.']);
        $this->post(route('ac-visits.complete', $o), ['done_at' => '2026-11-02'])->assertSessionHasErrors('status');
        $this->assertSame('draft', $o->fresh()->status);

        $this->post(route('ac-visits.send', $o))->assertSessionHasNoErrors();
        $this->post(route('ac-visits.complete', $o), ['done_at' => '2026-11-02'])->assertSessionHasErrors('status');
        $this->post(route('ac-visits.confirm', $o))->assertSessionHasNoErrors();
        Carbon::setTestNow('2026-11-03 14:00:00');
        $this->post(route('ac-visits.complete', $o), ['done_at' => ''])->assertSessionHasErrors(['done_at' => 'Isi tanggal selesai.']);
        $this->post(route('ac-visits.complete', $o), ['done_at' => '2026-11-03'])->assertSessionHasNoErrors();
        $this->assertSame('done', $o->fresh()->status);
        $this->get(route('ac-visits.show', $o))->assertOk()->assertSee('Selesai')->assertDontSee('Tandai selesai');
    }

    public function test_reschedule_and_cancel_need_a_reason_and_show_in_the_history(): void
    {
        $o = $this->order();
        $this->actingAs($this->admin);

        $this->post(route('ac-visits.reschedule', $o), ['date' => '2026-11-10', 'reason' => ''])->assertSessionHasErrors(['reason' => 'Alasan wajib diisi.']);
        $this->post(route('ac-visits.reschedule', $o), ['date' => '', 'reason' => 'Vendor sakit'])->assertSessionHasErrors(['date' => 'Isi tanggal baru.']);
        $this->post(route('ac-visits.reschedule', $o), ['date' => '2026-11-07', 'reason' => 'Vendor sakit'])->assertSessionHasErrors('date');   // Sabtu
        $this->post(route('ac-visits.reschedule', $o), ['date' => '2026-11-10', 'time' => '10:30', 'reason' => 'Vendor sakit'])->assertSessionHasNoErrors()
            ->assertSessionHas('status', fn ($m) => str_contains($m, 'Selasa, 10-11-2026 pukul 10.30'));

        $page = $this->get(route('ac-visits.show', $o))->assertOk();
        $page->assertSee('Dijadwal ulang')->assertSee('Riwayat jadwal ulang')->assertSee('Vendor sakit')->assertSee('03-11-2026 09.00')->assertSee('10-11-2026 10.30');
        $this->post(route('ac-visits.confirm', $o))->assertSessionHasErrors('status');   // harus dikirim ulang dulu

        $this->post(route('ac-visits.cancel', $o), ['reason' => 'x'])->assertSessionHasErrors('reason');
        $this->post(route('ac-visits.cancel', $o), ['reason' => 'Kontrak vendor berakhir'])->assertSessionHasNoErrors();
        $this->get(route('ac-visits.show', $o))->assertSee('Dibatalkan')->assertSee('Kontrak vendor berakhir');
        $this->get(route('ac-visits.index'))->assertSee('Dibatalkan');
    }

    public function test_photos_are_optional_private_and_checked(): void
    {
        $o = $this->order(1);
        $line = $o->lines()->first();
        $this->actingAs($this->admin);
        $post = fn ($file, string $kind = 'before') => $this->post(route('ac-visits.photo', $o), ['line_id' => $line->id, 'kind' => $kind, 'photo' => $file]);

        $post(UploadedFile::fake()->image('a.jpg'))->assertSessionHasErrors('photo');   // belum dikonfirmasi vendor
        AcVisits::send($o, $this->admin);
        AcVisits::confirm($o, $this->admin);

        $post(UploadedFile::fake()->create('dok.pdf', 20, 'application/pdf'))->assertSessionHasErrors(['photo' => 'Foto harus berupa JPG atau PNG.']);
        $post(UploadedFile::fake()->image('besar.png')->size(5121))->assertSessionHasErrors(['photo' => 'Foto paling besar 5 MB.']);
        $post(UploadedFile::fake()->create('skrip.jpg', 10, 'text/html'))->assertSessionHasErrors('photo');
        $this->post(route('ac-visits.photo', $o), ['line_id' => $line->id, 'kind' => 'samping', 'photo' => UploadedFile::fake()->image('a.jpg')])->assertSessionHasErrors('kind');
        $this->assertSame([], Storage::disk('ga')->allFiles());

        $post(UploadedFile::fake()->image('sebelum.jpg', 400, 300))->assertSessionHasNoErrors();
        $post(UploadedFile::fake()->image('sesudah.png', 400, 300), 'after')->assertSessionHasNoErrors();
        $line = $line->fresh();
        $this->assertCount(2, Storage::disk('ga')->allFiles());

        $this->get(route('ac-visits.file', [$line, 'before']))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get(route('ac-visits.file', [$line, 'after']))->assertOk();
        $this->get(route('ac-visits.show', $o))->assertSee('sebelum.jpg')->assertSee('sesudah.png');

        $other = $this->order(1, '2026-11-04')->lines()->first();
        $this->get(route('ac-visits.file', [$other, 'before']))->assertNotFound();   // tanpa foto
        $this->post(route('ac-visits.photo', $o), ['line_id' => $other->id, 'kind' => 'before', 'photo' => UploadedFile::fake()->image('x.jpg')])->assertNotFound();   // baris milik pesanan lain
    }

    public function test_photo_files_are_only_for_users_with_the_visit_module(): void
    {
        $o = $this->order(1);
        AcVisits::send($o, $this->admin);
        AcVisits::confirm($o, $this->admin);
        $line = $o->lines()->first();
        AcVisits::attachPhoto($line, 'before', UploadedFile::fake()->image('a.jpg'));
        $line = $line->fresh();

        $this->actingAs($this->acUser(['GAV' => 'ro']))->get(route('ac-visits.file', [$line, 'before']))->assertOk();
        $this->actingAs($this->acUser(['GAS' => 'rw']))->get(route('ac-visits.file', [$line, 'before']))->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('ac-visits.file', [$line, 'before']))->assertForbidden();
        auth()->logout();
        $this->get(route('ac-visits.file', [$line, 'before']))->assertRedirect(route('login'));
    }

    public function test_the_print_shows_the_mode_the_units_and_the_signature_boxes(): void
    {
        $o = $this->order(2);
        $res = $this->actingAs($this->admin)->get(route('ac-visits.print', $o))->assertOk();

        $res->assertSee('Berita Acara Perawatan AC')->assertSee($o->no)->assertSee('MODE: DRAF', false)->assertSee('Selasa, 03-11-2026 pukul 09.00', false)->assertSee('CV Sejuk Contoh')
            ->assertSee('Petugas Vendor')->assertSee('PIC Area')->assertSee('Petugas GA')->assertSee('Sari GA')->assertSee('RM · AC-0001', false)->assertSee('Cuci rutin');
        $res->assertSee('Pesanan belum Selesai', false);

        AcVisits::cancel($o, 'Dibatalkan uji', $this->admin);
        $this->get(route('ac-visits.print', $o))->assertSee('MODE: DIBATALKAN', false)->assertSee('PESANAN DIBATALKAN', false);
    }

    public function test_a_read_only_group_sees_the_order_without_action_buttons_and_cannot_post(): void
    {
        $o = $this->order();
        $ro = $this->acUser(['GAV' => 'ro']);
        $this->actingAs($ro)->get(route('ac-visits.show', $o))->assertOk()->assertSee($o->no)->assertDontSee('Tandai selesai')->assertDontSee('data-dl-open="send-modal"', false)->assertDontSee('Batalkan kunjungan');
        $this->get(route('ac-visits.index'))->assertOk();
        $this->get(route('ac-visits.print', $o))->assertOk();
        foreach (['send', 'confirm', 'cancel'] as $action) {
            $this->post(route("ac-visits.{$action}", $o), ['reason' => 'Uji coba'])->assertForbidden();
        }
        $this->post(route('ac-visits.complete', $o), ['done_at' => '2026-11-02'])->assertForbidden();
        $this->post(route('ac-visits.reschedule', $o), ['date' => '2026-11-10', 'reason' => 'Uji coba'])->assertForbidden();
        $this->post(route('ac-visits.photo', $o), [])->assertForbidden();
        Mail::assertNothingSent();
        $this->assertSame('draft', $o->fresh()->status);

        $this->actingAs($this->acUser(['GAS' => 'rw']))->get(route('ac-visits.index'))->assertForbidden();
    }

    public function test_the_schedule_screen_links_to_the_visit_for_users_who_may_open_it(): void
    {
        $o = $this->order(1);
        $this->actingAs($this->acUser(['GAS' => 'ro', 'GAV' => 'ro']))->get(route('ac-schedule.index'))->assertSee(route('ac-visits.show', $o), false);
        $this->actingAs($this->acUser(['GAS' => 'ro']))->get(route('ac-schedule.index'))->assertDontSee(route('ac-visits.show', $o), false)->assertSee($o->no);
    }

    public function test_the_visit_screens_stay_under_the_query_budget_and_do_not_grow_with_the_lines(): void
    {
        Constants::set('ga.ac.capacity', 10, 'ga');
        $few = $this->order(2);
        $many = $this->order(8, '2026-11-04');
        $this->actingAs($this->admin);
        $measure = function (string $url) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get($url)->assertOk();

            return count(DB::getQueryLog());
        };
        foreach ([route('ac-visits.index'), route('ac-visits.show', $few), route('ac-visits.print', $few)] as $url) {
            $this->get($url);   // pemanasan
        }

        $this->assertLessThan(30, $measure(route('ac-visits.index')));
        $a = $measure(route('ac-visits.show', $few));
        $b = $measure(route('ac-visits.show', $many));
        $this->assertSame($a, $b, 'kueri layar Kunjungan tidak naik menurut jumlah AC');
        $this->assertLessThan(30, $b);
        $this->assertSame($measure(route('ac-visits.print', $few)), $measure(route('ac-visits.print', $many)));
        $this->assertSame(AcCare::capacity(), 10);
    }
}
