<?php

namespace App\Services;

use Carbon\CarbonInterface;

/* REMARK
 * Tujuan       : Pembuat pesan pemberitahuan kunjungan AC (MURNI: hanya teks dari data, tanpa basis data / jaringan): pesan untuk vendor (tanggal, jam mulai, jumlah AC, daftar AC dengan area, ruang, tipe, PIC,
 *                permintaan foto sebelum / sesudah dan berita acara), pesan salinan untuk PIC area (dikirim sehari sebelumnya pukul 15.00), judul e-mail, normalisasi nomor WhatsApp (08xx -> 628xx) dan tautan wa.me.
 *                Isi pesan hanya teks kunjungan (tanggal, tempat, nama); tidak ada data sensitif di URL selain teks pesan itu sendiri.
 * Sumber hulu  : array data dari AcVisits::noticeData (lihat metode); tanpa tabel.
 * Output       : vendor(), pic(), subject(), waNumber(), waLink(), dateText().
 * Dipakai oleh : AcVisits, AcVisitController (pratinjau), Mailable AcVisitNotice.
 * Status       : Aktif.
 */
final class AcMessage
{
    /** "Senin, 05-11-2026". */
    public static function dateText(CarbonInterface $date): string
    {
        return AcCare::DAYS[$date->dayOfWeekIso].', '.$date->format('d-m-Y');
    }

    /**
     * Nomor WhatsApp internasional tanpa tanda: 0812-3456-7890 -> 6281234567890; +62 812... -> 62812...; 812... -> 62812.... Tidak masuk akal (kurang dari 9 atau lebih dari 15 angka) = null.
     */
    public static function waNumber(?string $raw): ?string
    {
        $d = preg_replace('/\D+/', '', (string) $raw);
        if ($d === '') {
            return null;
        }
        $d = str_starts_with($d, '00') ? substr($d, 2) : $d;
        $d = match (true) {
            str_starts_with($d, '620') => '62'.substr($d, 3),
            str_starts_with($d, '0') => '62'.substr($d, 1),
            str_starts_with($d, '8') => '62'.$d,
            default => $d,
        };

        return strlen($d) >= 9 && strlen($d) <= 15 ? $d : null;
    }

    /** Tautan wa.me dengan pesan terisi; null bila nomornya tidak sah. */
    public static function waLink(?string $phone, string $text): ?string
    {
        $n = self::waNumber($phone);

        return $n ? 'https://wa.me/'.$n.'?text='.rawurlencode($text) : null;
    }

    public static function subject(array $d): string
    {
        if (($d['type'] ?? 'preventive') === 'breakdown') {
            return (($d['priority'] ?? null) === 'urgent' ? 'MENDESAK - ' : '').'Perbaikan AC '.self::dateText($d['date']).' ('.count($d['lines']).' unit) - '.$d['no'];
        }

        return 'Jadwal perawatan AC '.self::dateText($d['date']).' ('.count($d['lines']).' unit) - '.$d['no'];
    }

    /**
     * Pesan untuk vendor.
     *
     * @param  array{no: string, date: CarbonInterface, time: string, company: string, sender: string, greeting: string, lines: list<array>}  $d  lines: area, room, room_code, asset_code, type, job, pic
     */
    public static function vendor(array $d): string
    {
        $breakdown = ($d['type'] ?? 'preventive') === 'breakdown';
        $out = [
            'Yth. '.$d['greeting'].',',
            '',
            $breakdown ? 'Kami membutuhkan perbaikan AC'.(($d['priority'] ?? null) === 'urgent' ? ' (MENDESAK)' : '').' di '.$d['company'].' sebagai berikut.' : 'Kami menjadwalkan perawatan AC di '.$d['company'].' sebagai berikut.',
            ...($breakdown ? ['Keluhan      : '.$d['complaint']] : []),
            'Tanggal      : '.self::dateText($d['date']),
            'Jam mulai    : '.AcCare::timeText($d['time']),
            'Jumlah AC    : '.count($d['lines']).' unit',
            'No. pesanan  : '.$d['no'],
            '',
            'Daftar AC:',
        ];
        foreach ($d['lines'] as $i => $l) {
            $out[] = ($i + 1).'. '.self::lineText($l, true);
        }
        array_push($out,
            '',
            'Mohon:',
            '- Foto kondisi tiap AC sebelum dan sesudah dikerjakan.',
            '- Berita acara ditandatangani PIC area setelah pekerjaan selesai.',
            '- Balas pesan ini untuk mengonfirmasi kedatangan.',
            '',
            'Terima kasih.',
            $d['sender'],
        );
        $breakdown && array_splice($out, (int) array_search('- Berita acara ditandatangani PIC area setelah pekerjaan selesai.', $out, true), 0, '- Sebutkan penyebab kerusakan dan spare-part yang diganti beserta biayanya.');

        return implode("\n", $out);
    }

    /**
     * Pesan salinan untuk satu PIC area (hanya AC di ruang yang dipegangnya).
     *
     * @param  array{no: string, date: CarbonInterface, time: string, company: string, sender: string, vendor: string, pic: string, lines: list<array>}  $d
     */
    public static function pic(array $d): string
    {
        $out = [
            'Yth. '.$d['pic'].',',
            '',
            'Pengingat: vendor '.$d['vendor'].' akan datang pada '.self::dateText($d['date']).' mulai pukul '.AcCare::timeText($d['time']).(($d['type'] ?? 'preventive') === 'breakdown' ? ' untuk memperbaiki ' : ' untuk membersihkan ').count($d['lines']).' AC di area Anda:',
        ];
        foreach ($d['lines'] as $i => $l) {
            $out[] = ($i + 1).'. '.self::lineText($l, false);
        }
        array_push($out,
            '',
            'Mohon ruangan dapat diakses dan barang di sekitar AC dirapikan. Bapak/Ibu diminta menandatangani berita acara setelah pekerjaan selesai.',
            '',
            'Terima kasih. ('.$d['company'].', no. '.$d['no'].')',
            $d['sender'],
        );

        return implode("\n", $out);
    }

    // "Kantor Lt. 1 · Ruang Meeting (MTG) · AC-0004 · Daikin FTV25 1 PK · Cuci rutin · PIC Budi".
    private static function lineText(array $l, bool $withPic): string
    {
        $parts = [$l['area'], $l['room'].' ('.$l['room_code'].')', $l['asset_code'], $l['type'], $l['job']];
        $withPic && $l['pic'] && $parts[] = 'PIC '.$l['pic'];

        return implode(' · ', $parts);
    }
}
