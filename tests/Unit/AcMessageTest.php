<?php

namespace Tests\Unit;

use App\Services\AcMessage;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

/* REMARK
 * Tujuan       : Menjaga pembuat pesan pemberitahuan kunjungan AC yang murni (tanpa basis data): normalisasi nomor WhatsApp (08xx -> 628xx), tautan wa.me terisi, isi pesan vendor dan pesan salinan PIC.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class AcMessageTest extends TestCase
{
    private function data(): array
    {
        return [
            'no' => 'ACV-2611-0001', 'date' => Carbon::parse('2026-11-09'), 'time' => '09:00', 'company' => 'PT Contoh Jaya', 'sender' => 'Sari (GA)', 'greeting' => 'Pak Andi', 'vendor' => 'CV Sejuk Contoh',
            'lines' => [
                ['area' => 'Kantor Lt. 1', 'room' => 'Ruang Meeting', 'room_code' => 'MTG', 'asset_code' => 'AC-0004', 'type' => 'Daikin FTV25 1 PK', 'job' => 'Cuci rutin', 'pic' => 'Budi'],
                ['area' => 'Gudang', 'room' => 'Kantor Gudang', 'room_code' => 'KG', 'asset_code' => 'AC-0007', 'type' => 'LG 2 PK', 'job' => 'Servis besar', 'pic' => null],
            ],
        ];
    }

    public function test_whatsapp_numbers_are_normalised_to_the_international_form(): void
    {
        foreach ([
            '0812-3456-7890' => '6281234567890', '0812 3456 7890' => '6281234567890', '+62 812-3456-7890' => '6281234567890', '6281234567890' => '6281234567890',
            '81234567890' => '6281234567890', '(0812) 3456.7890' => '6281234567890', '0062 812 3456 7890' => '6281234567890', '620812-3456-7890' => '6281234567890',
            '+1 415 555 2671' => '14155552671',
        ] as $in => $out) {
            $this->assertSame($out, AcMessage::waNumber($in), $in);
        }
        foreach ([null, '', '   ', 'abc', '12345', '0812', str_repeat('9', 16)] as $bad) {
            $this->assertNull(AcMessage::waNumber($bad), (string) $bad);
        }
    }

    public function test_the_whatsapp_link_carries_only_the_number_and_the_encoded_text(): void
    {
        $link = AcMessage::waLink('0812-3456-7890', "Halo & selamat\npagi 100%");

        $this->assertSame('https://wa.me/6281234567890?text=Halo%20%26%20selamat%0Apagi%20100%25', $link);
        $this->assertNull(AcMessage::waLink('xx', 'teks'));
        $this->assertNull(AcMessage::waLink(null, 'teks'));
    }

    public function test_the_vendor_message_lists_date_time_count_and_every_unit_with_its_place_and_pic(): void
    {
        $text = AcMessage::vendor($this->data());

        $this->assertStringStartsWith('Yth. Pak Andi,', $text);
        foreach ([
            'PT Contoh Jaya', 'Tanggal      : Senin, 09-11-2026', 'Jam mulai    : 09.00', 'Jumlah AC    : 2 unit', 'No. pesanan  : ACV-2611-0001',
            '1. Kantor Lt. 1 · Ruang Meeting (MTG) · AC-0004 · Daikin FTV25 1 PK · Cuci rutin · PIC Budi',
            '2. Gudang · Kantor Gudang (KG) · AC-0007 · LG 2 PK · Servis besar',
            'Foto kondisi tiap AC sebelum dan sesudah', 'Berita acara ditandatangani PIC', 'Sari (GA)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $text);
        }
        $this->assertStringNotContainsString('PIC null', $text);
        $this->assertStringNotContainsString('· PIC'."\n", $text, 'AC tanpa PIC tidak menulis PIC kosong');
    }

    public function test_the_pic_copy_lists_only_that_pics_units_and_names_the_vendor(): void
    {
        $d = $this->data();
        $text = AcMessage::pic(['pic' => 'Budi', 'lines' => [$d['lines'][0]]] + $d);

        $this->assertStringStartsWith('Yth. Budi,', $text);
        $this->assertStringContainsString('vendor CV Sejuk Contoh akan datang pada Senin, 09-11-2026 mulai pukul 09.00 untuk membersihkan 1 AC', $text);
        $this->assertStringContainsString('1. Kantor Lt. 1 · Ruang Meeting (MTG) · AC-0004', $text);
        $this->assertStringNotContainsString('AC-0007', $text);
        $this->assertStringContainsString('ACV-2611-0001', $text);
    }

    public function test_the_email_subject_names_the_day_and_the_unit_count(): void
    {
        $this->assertSame('Jadwal perawatan AC Senin, 09-11-2026 (2 unit) - ACV-2611-0001', AcMessage::subject($this->data()));
    }
}
