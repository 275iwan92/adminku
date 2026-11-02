<?php

namespace App\Support;

use App\Models\AcOrder;
use App\Models\AcOrderLine;
use App\Services\AcCare;
use App\Services\AcMessage;
use Devlib\Locale;

/* REMARK
 * Tujuan       : Data cetakan BERITA ACARA kunjungan perawatan AC untuk x-dl::print-sheet (A4 potret): kop perusahaan (otomatis), nomor pesanan, tanggal / jam / vendor, MODE (status pesanan),
 *                tabel AC (kode ruang · kode aset, area dan ruang, tipe, pekerjaan, tanggal selesai, kondisi / catatan untuk ditulis tangan) dan kotak tanda tangan (petugas vendor, PIC area, petugas GA).
 * Sumber hulu  : AcOrder (vendor, lines.unit.room.area), Company (kop lewat DevLib).
 * Output       : array prop untuk x-dl::print-sheet (title, code, info, sections, signatures, note, landscape).
 * Dipakai oleh : AcVisitController@print (ac-visits.print).
 * Status       : Aktif.
 */
final class AcVisitPrint
{
    /** @return array<string, mixed> */
    public function report(AcOrder $order, string $gaName): array
    {
        $order->loadMissing('vendor', 'lines.unit.room.area');
        $lines = $order->lines->sortBy(fn (AcOrderLine $l) => $l->unit->room->area->name.'|'.$l->unit->room->code.'|'.$l->unit->asset_code)->values();
        $rows = $lines->map(fn (AcOrderLine $l, int $i) => [
            'no' => $i + 1, 'ac' => ['text' => $l->unit->label(), 'sub' => $l->unit->typeText()], 'place' => ['text' => $l->unit->room->area->name, 'sub' => $l->unit->room->name.($l->unit->room->pic_name ? ' · PIC '.$l->unit->room->pic_name : '')],
            'job' => AcCare::JOBS[$l->job], 'done' => $l->done_at ? Locale::d($l->done_at) : '', 'note' => ['text' => ' ', 'class' => 'sstock-write'],
        ])->all();
        $pics = $lines->map(fn (AcOrderLine $l) => $l->unit->room->pic_name)->filter()->unique()->values();

        return [
            'title' => 'Berita Acara Perawatan AC', 'code' => $order->no, 'landscape' => false,
            'info' => [
                'Tanggal kunjungan: '.AcMessage::dateText($order->visit_date).' pukul '.AcCare::timeText($order->timeText()),
                'Vendor: '.$order->vendor->name.($order->vendor->contact_name ? ' · '.$order->vendor->contact_name : ''),
                'MODE: '.strtoupper(AcCare::ORDER_STATUS[$order->status]).' · '.$lines->count().' unit AC',
            ],
            'sections' => [
                ['title' => 'Daftar AC', 'blocks' => [['type' => 'table', 'empty' => 'Pesanan ini belum berisi AC.', 'columns' => [
                    ['key' => 'no', 'label' => 'No.', 'width' => 9, 'align' => 'right'], ['key' => 'ac', 'label' => 'Kode ruang · kode aset', 'width' => 44], ['key' => 'place', 'label' => 'Area / ruang', 'width' => 44],
                    ['key' => 'job', 'label' => 'Pekerjaan', 'width' => 24], ['key' => 'done', 'label' => 'Selesai', 'width' => 22], ['key' => 'note', 'label' => 'Kondisi / catatan'],
                ], 'rows' => $rows]]],
                ['title' => 'Pernyataan', 'blocks' => [['type' => 'text', 'text' => 'Pekerjaan perawatan AC pada daftar di atas telah dilaksanakan oleh vendor dan diperiksa bersama PIC area. Kondisi dan catatan temuan ditulis pada kolom Kondisi / catatan.']]],
            ],
            'note' => $order->status === 'cancelled' ? 'PESANAN DIBATALKAN: dokumen ini tidak berlaku.' : ($order->status === 'done' ? null : 'Pesanan belum Selesai: dokumen ini diisi saat kunjungan.'),
            'signatures' => [['role' => 'Petugas Vendor', 'name' => ''], ['role' => 'PIC Area', 'name' => $pics->count() === 1 ? $pics[0] : ''], ['role' => 'Petugas GA', 'name' => $gaName]],
        ];
    }
}
