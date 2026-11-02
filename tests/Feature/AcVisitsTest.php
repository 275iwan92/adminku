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
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\AcFixtures;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga siklus kunjungan vendor AC: urutan tahap ditegakkan (Draf > Terkirim > Dikonfirmasi vendor > Selesai, tidak bisa melompat), Kirim mengirim e-mail (Mail::fake) sekali dan mencatat waktu dan pengirim,
 *                gagal kirim tidak mengubah status, jadwal ulang menyimpan riwayat dan menegakkan hari / jam / kapasitas, Selesai mencatat tanggal tiap AC, pesan salinan PIC, dan foto bukti opsional (disk privat).
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class AcVisitsTest extends TestCase
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

    private function fails(callable $fn, string $key, string $contains): void
    {
        try {
            $fn();
        } catch (ValidationException $e) {
            $this->assertStringContainsString($contains, $e->errors()[$key][0] ?? '(tidak ada galat untuk '.$key.')');

            return;
        }
        $this->fail('Seharusnya ditolak: '.$contains);
    }

    private function order(int $units = 2, string $date = '2026-11-03'): AcOrder
    {
        return $this->acOrder($this->acVendor(), array_map(fn () => $this->acLate(), range(1, $units)), $date, $this->admin);
    }

    public function test_the_stages_run_in_order_and_cannot_be_skipped(): void
    {
        $o = $this->order();
        $this->assertSame('draft', $o->status);

        $this->fails(fn () => AcVisits::confirm($o, $this->admin), 'status', 'Tandai dikonfirmasi vendor tidak bisa: pesanan '.$o->no.' berstatus Draf. Urutan tahap: Draf, Terkirim, Dikonfirmasi vendor, Selesai.');
        $this->fails(fn () => AcVisits::complete($o, '2026-11-02', $this->admin), 'status', 'berstatus Draf');

        AcVisits::send($o, $this->admin);
        $this->assertSame('sent', $o->fresh()->status);
        $this->fails(fn () => AcVisits::complete($o, '2026-11-02', $this->admin), 'status', 'berstatus Terkirim');

        AcVisits::confirm($o, $this->admin);
        $o = $o->fresh();
        $this->assertSame(['confirmed', $this->admin->id], [$o->status, $o->confirmed_by]);
        $this->assertNotNull($o->confirmed_at);
        $this->fails(fn () => AcVisits::confirm($o, $this->admin), 'status', 'berstatus Dikonfirmasi vendor');
        $this->fails(fn () => AcVisits::send($o, $this->admin), 'status', 'Kirim tidak bisa');

        Carbon::setTestNow('2026-11-03 15:00:00');
        AcVisits::complete($o, '2026-11-03', $this->admin);
        $o = $o->fresh();
        $this->assertSame(['done', $this->admin->id], [$o->status, $o->done_by]);
        $this->fails(fn () => AcVisits::cancel($o, 'Terlambat batal', $this->admin), 'status', 'berstatus Selesai');
        $this->fails(fn () => AcVisits::reschedule($o, '2026-11-10', null, 'Mau pindah', $this->admin), 'status', 'berstatus Selesai');
    }

    public function test_send_emails_the_vendor_once_and_records_who_and_when(): void
    {
        $o = $this->order();
        $result = AcVisits::send($o, $this->admin);

        $this->assertSame(['vendor@example.test', true], [$result['email'], $result['wa']]);
        Mail::assertSent(AcVisitNotice::class, 1);
        Mail::assertSent(AcVisitNotice::class, function (AcVisitNotice $m) use ($o) {
            return $m->hasTo('vendor@example.test')
                && str_contains($m->subjectText, 'Jadwal perawatan AC Selasa, 03-11-2026 (2 unit)') && str_contains($m->subjectText, $o->no)
                && str_contains($m->body, 'Yth. Pak Andi,') && str_contains($m->body, 'Jam mulai    : 09.00') && str_contains($m->body, 'Sari GA');
        });
        $o = $o->fresh();
        $this->assertSame(['sent', $this->admin->id, '2026-11-02 08:00:00'], [$o->status, $o->sent_by, $o->sent_at->format('Y-m-d H:i:s')]);

        AcVisits::send($o, $this->admin);   // kirim ulang dari Terkirim diperbolehkan
        Mail::assertSent(AcVisitNotice::class, 2);
    }

    public function test_nothing_is_sent_until_the_button_is_pressed(): void
    {
        $this->order();
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    public function test_a_failed_mail_leaves_the_order_as_it_was(): void
    {
        $o = $this->order();
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('SMTP mati'));

        $this->fails(fn () => AcVisits::send($o, $this->admin), 'status', 'E-mail belum bisa dikirim');
        $o = $o->fresh();
        $this->assertSame(['draft', null], [$o->status, $o->sent_at]);
    }

    public function test_a_vendor_needs_at_least_one_channel_and_a_whatsapp_only_vendor_sends_without_mail(): void
    {
        $none = $this->acVendor('CV Tanpa Kontak', ['phone' => null, 'email' => null]);
        $o = $this->acOrder($none, [$this->acLate()], '2026-11-03', $this->admin);
        $this->fails(fn () => AcVisits::send($o, $this->admin), 'status', 'belum punya e-mail maupun nomor WhatsApp');
        $this->assertSame('draft', $o->fresh()->status);

        $wa = $this->acVendor('CV Hanya WA', ['email' => null]);
        $o2 = $this->acOrder($wa, [$this->acLate()], '2026-11-04', $this->admin);
        $this->assertSame([null, true], array_values(AcVisits::send($o2, $this->admin)));
        Mail::assertNothingSent();
        $this->assertSame('sent', $o2->fresh()->status);

        $badWa = $this->acVendor('CV Nomor Rusak', ['phone' => '123', 'email' => 'x@example.test']);
        $o3 = $this->acOrder($badWa, [$this->acLate()], '2026-11-05', $this->admin);
        $this->assertSame(['x@example.test', false], array_values(AcVisits::send($o3, $this->admin)));
    }

    public function test_an_order_in_the_past_cannot_be_sent(): void
    {
        $o = $this->order();
        Carbon::setTestNow('2026-11-04 08:00:00');
        $this->fails(fn () => AcVisits::send($o, $this->admin), 'status', 'sudah lewat. Jadwal ulang dulu');
    }

    public function test_reschedule_keeps_history_enforces_the_rules_and_needs_a_resend(): void
    {
        $o = $this->order();
        AcVisits::send($o, $this->admin);
        AcVisits::confirm($o, $this->admin);

        $this->fails(fn () => AcVisits::reschedule($o, '2026-11-10', null, '  ', $this->admin), 'reason', 'Alasan jadwal ulang wajib');
        $this->fails(fn () => AcVisits::reschedule($o, '2026-11-01', null, 'Maju', $this->admin), 'date', 'masa lalu');
        $this->fails(fn () => AcVisits::reschedule($o, '2026-11-08', null, 'Minggu', $this->admin), 'date', 'Hari Minggu tidak diizinkan');
        $this->fails(fn () => AcVisits::reschedule($o, '2026-11-03', null, 'Sama', $this->admin), 'date', 'sama dengan jadwal sekarang');

        AcVisits::reschedule($o, '2026-11-10', '10:30', 'Vendor ada pekerjaan lain', $this->admin);
        $o = $o->fresh();
        $this->assertSame(['rescheduled', '2026-11-10', '10:30', 'Vendor ada pekerjaan lain', null, null], [$o->status, $o->visit_date->toDateString(), $o->timeText(), $o->reschedule_reason, $o->sent_at, $o->confirmed_at]);
        $h = $o->reschedules()->first();
        $this->assertSame(['2026-11-03', '09:00', '2026-11-10', '10:30', 'Vendor ada pekerjaan lain', $this->admin->id], [$h->from_date->toDateString(), substr($h->from_time, 0, 5), $h->to_date->toDateString(), substr($h->to_time, 0, 5), $h->reason, $h->created_by]);

        $this->fails(fn () => AcVisits::confirm($o, $this->admin), 'status', 'berstatus Dijadwal ulang');   // harus dikirim ulang dulu
        AcVisits::send($o, $this->admin);
        AcVisits::reschedule($o, '2026-11-11', null, 'Pindah lagi', $this->admin);
        $this->assertSame(2, $o->reschedules()->count());
        $this->assertSame('rescheduled', $o->fresh()->status);
        AcVisits::send($o, $this->admin);
        AcVisits::confirm($o, $this->admin);
        $this->assertSame('confirmed', $o->fresh()->status);
    }

    public function test_reschedule_respects_the_capacity_of_the_new_day_but_not_its_own_lines(): void
    {
        $vendor = $this->acVendor();
        $a = $this->acOrder($vendor, [$this->acLate(), $this->acLate()], '2026-11-03', $this->admin);
        $this->acOrder($vendor, [$this->acLate(), $this->acLate()], '2026-11-04', $this->admin);

        $this->fails(fn () => AcVisits::reschedule($a, '2026-11-04', null, 'Pindah', $this->admin), 'date', 'hanya muat 1 AC lagi');
        $this->assertSame('2026-11-03', $a->fresh()->visit_date->toDateString());
        AcVisits::reschedule($a, '2026-11-03', '14:00', 'Hanya ganti jam', $this->admin);   // hari yang sama: baris sendiri tidak dihitung dua kali
        $this->assertSame('14:00', $a->fresh()->timeText());
    }

    public function test_cancel_needs_a_reason_and_keeps_the_record(): void
    {
        $o = $this->order();
        $this->fails(fn () => AcVisits::cancel($o, 'x', $this->admin), 'reason', 'Alasan pembatalan wajib');
        AcVisits::cancel($o, 'Vendor tidak bisa hadir', $this->admin);
        $o = $o->fresh();
        $this->assertSame(['cancelled', 'Vendor tidak bisa hadir', $this->admin->id], [$o->status, $o->cancel_reason, $o->cancelled_by]);
        $this->assertSame(2, $o->lines()->count(), 'catatan batal tetap tersimpan');
        $this->fails(fn () => AcVisits::send($o, $this->admin), 'status', 'berstatus Dibatalkan');
    }

    public function test_completing_records_the_date_of_every_unit_within_the_allowed_window(): void
    {
        $o = $this->order();
        AcVisits::send($o, $this->admin);
        AcVisits::confirm($o, $this->admin);

        $this->fails(fn () => AcVisits::complete($o, '2026-11-03', $this->admin), 'done_at', 'setelah hari ini');   // kunjungan besok
        Carbon::setTestNow('2026-11-04 17:00:00');
        $this->fails(fn () => AcVisits::complete($o, '2026-11-02', $this->admin), 'done_at', 'sebelum tanggal kunjungan');
        $this->fails(fn () => AcVisits::complete($o, 'kemarin', $this->admin), 'done_at', 'tidak valid');

        AcVisits::complete($o, '2026-11-04', $this->admin);   // vendor datang sehari lebih lambat
        $this->assertSame(['2026-11-04', '2026-11-04'], $o->lines()->get()->map(fn ($l) => $l->done_at->toDateString())->all());
        $unit = $o->lines()->first()->unit;
        $this->assertSame('2027-02-04', AcCare::overview(collect([$unit]))[$unit->id]['next_wash']->toDateString(), 'jatuh tempo berikutnya dihitung dari tanggal selesai');
    }

    public function test_pic_notices_group_units_by_pic_and_name_the_day_before_at_15(): void
    {
        Constants::set('ga.ac.capacity', 5, 'ga');
        $area = $this->acArea();
        $meeting = $this->acRoom($area, 'Ruang Meeting', ['pic_name' => 'Budi', 'pic_phone' => '0812-3456-7001']);
        $dir = $this->acRoom($area, 'Ruang Direksi', ['pic_name' => 'Budi', 'pic_phone' => '0812-3456-7001']);   // PIC yang sama: digabung
        $office = $this->acRoom($area, 'Ruang Admin', ['pic_name' => 'Rina', 'pic_phone' => '0813-1111-2222']);
        $none = $this->acRoom($area, 'Gudang Kecil', ['pic_name' => null, 'pic_phone' => null]);
        $vendor = $this->acVendor();
        $o = $this->acOrder($vendor, [$this->acLate($meeting), $this->acLate($dir), $this->acLate($office)], '2026-11-03', $this->admin);
        AcCare::schedule($vendor, '2026-11-03', null, [$this->acLate($none)->id], $this->admin);   // masuk pesanan Draf yang sama
        $r = AcVisits::picNotices($o->fresh(), 'Sari GA');

        $this->assertSame('2026-11-02 15:00:00', $r['send_at']->format('Y-m-d H:i:s'));
        $this->assertEquals(['Budi' => 2, 'Rina' => 1], collect($r['notices'])->pluck('count', 'pic')->all());
        $this->assertSame(['Gudang Kecil (GK)'], $r['missing']);
        $budi = collect($r['notices'])->firstWhere('pic', 'Budi');
        $this->assertStringContainsString('Yth. Budi,', $budi['text']);
        $this->assertStringContainsString('membersihkan 2 AC', $budi['text']);
        $this->assertStringStartsWith('https://wa.me/6281234567001?text=', $budi['wa']);
        Mail::assertNothingSent();   // hanya disiapkan, tidak terkirim otomatis
    }

    public function test_the_vendor_message_has_a_whatsapp_link_with_the_text_filled_in(): void
    {
        $o = $this->order();
        $m = AcVisits::vendorMessage($o, 'Sari GA');

        $this->assertStringStartsWith('https://wa.me/6281234567000?text=', $m['wa']);
        $this->assertSame($m['text'], rawurldecode(substr($m['wa'], strlen('https://wa.me/6281234567000?text='))));
        $this->assertStringContainsString('Daikin FTV25 1 PK', $m['text']);
        $this->assertStringContainsString('Cuci rutin', $m['text']);
    }

    public function test_photos_are_optional_private_and_only_after_confirmation(): void
    {
        $o = $this->order(1);
        $line = $o->lines()->first();
        $photo = fn (string $n = 'foto.jpg') => UploadedFile::fake()->image($n, 640, 480);

        $this->fails(fn () => AcVisits::attachPhoto($line, 'before', $photo()), 'photo', 'setelah pesanan Dikonfirmasi');
        AcVisits::send($o, $this->admin);
        AcVisits::confirm($o, $this->admin);
        $this->assertSame([], Storage::disk('ga')->allFiles(), 'tanpa foto pun pesanan tetap bisa jalan');

        AcVisits::attachPhoto($line->fresh(), 'before', $photo('sebelum.jpg'));
        AcVisits::attachPhoto($line->fresh(), 'after', $photo('sesudah.png'));
        $line = $line->fresh();
        Storage::disk('ga')->assertExists($line->photo_before_path);
        Storage::disk('ga')->assertExists($line->photo_after_path);
        $this->assertSame(['sebelum.jpg', 'sesudah.png'], [$line->photo_before_name, $line->photo_after_name]);
        $this->assertStringStartsWith('ac/service/'.$o->id.'/', $line->photo_before_path);

        $old = $line->photo_before_path;
        AcVisits::attachPhoto($line, 'before', $photo('baru.jpg'));   // diganti: berkas lama dibuang
        Storage::disk('ga')->assertMissing($old);
        $this->assertCount(2, Storage::disk('ga')->allFiles());
        $this->fails(fn () => AcVisits::attachPhoto($line, 'samping', $photo()), 'kind', 'tidak dikenal');
    }
}
