<?php

namespace App\Support;

use App\Models\Location;
use App\Models\StockCount;
use App\Models\StockCountLine;
use App\Services\StockCounts;
use Devlib\Locale;

/* REMARK
 * Tujuan       : Data cetakan Stok Opname untuk x-dl::print-sheet (A4): (1) LEMBAR HITUNG yang dipakai orang gudang dengan kertas dan alat tulis, dikelompokkan per Lokasi lalu kode,
 *                kolom hasil / penghitung / catatan kosong, stok sistem hanya bila tidak buta; (2) BERITA ACARA (setelah Selesai; pratinjau saat Tinjau): ringkasan, tabel baris selisih beserta alasan, tiga kotak tanda tangan.
 * Sumber hulu  : StockCounts::ordered / summary, i_count_line (snapshot), m_location.
 * Output       : array prop untuk x-dl::print-sheet (title, code, info, sections, signatures, note, landscape).
 * Dipakai oleh : StockCountController@sheet / report (stock-counts/sheet, stock-counts/report).
 * Status       : Aktif.
 */
final class StockCountPrint
{
    // Sel isian tulis tangan: kelas sstock-write (resources/css/app.css) memberi tinggi baris cukup untuk ditulisi pena.
    private const BLANK = ['text' => ' ', 'class' => 'sstock-write'];

    public function __construct(private readonly StockCounts $counts) {}

    /** @return array<string, mixed> */
    public function sheet(StockCount $count): array
    {
        $names = Location::query()->pluck('name', 'code');
        $columns = array_values(array_filter([
            ['key' => 'no', 'label' => 'No.', 'width' => 9, 'align' => 'right'],
            ['key' => 'code', 'label' => 'Kode', 'width' => 34, 'mono' => true],
            ['key' => 'name', 'label' => 'Nama Barang'],
            ['key' => 'batch', 'label' => 'Batch (Kedaluwarsa)', 'width' => 26],
            ['key' => 'location', 'label' => 'Lokasi', 'width' => 22],
            ['key' => 'unit', 'label' => 'Satuan', 'width' => 14],
            $count->blind ? null : ['key' => 'system', 'label' => 'Stok Sistem', 'width' => 22, 'align' => 'right'],
            ['key' => 'counted', 'label' => 'Hasil Hitung', 'width' => 28],
            ['key' => 'counter', 'label' => 'Penghitung', 'width' => 26],
            ['key' => 'note', 'label' => 'Catatan', 'width' => 36],
        ]));

        $sections = [];
        foreach ($this->counts->ordered($count)->groupBy(fn (StockCountLine $l) => (string) ($l->item->location ?? '')) as $location => $lines) {
            $rows = $lines->values()->map(fn (StockCountLine $l, int $i) => [
                'no' => $i + 1, 'code' => $l->item->code, 'name' => $l->item->name, 'batch' => $l->item->expiry_date ? Locale::d($l->item->expiry_date) : '—',
                'location' => $location === '' ? '—' : $location, 'unit' => $l->item->unit, 'system' => $this->qty($l->system_qty), 'counted' => self::BLANK, 'counter' => self::BLANK, 'note' => self::BLANK,
            ])->all();
            $sections[] = ['title' => $location === '' ? 'Tanpa lokasi' : 'Lokasi '.$location.(isset($names[$location]) ? ' · '.$names[$location] : ''), 'blocks' => [['type' => 'table', 'columns' => $columns, 'rows' => $rows]]];
        }

        return [
            'title' => 'Lembar Hitung Stok Opname', 'code' => $count->doc_no, 'landscape' => true,
            'info' => ['Tanggal: '.Locale::d($count->count_date), 'Cakupan: '.$count->scopeLabel(), 'Jumlah batch: '.$this->counts->ordered($count)->count().($count->blind ? ' · hitung buta' : '')],
            'sections' => $sections,
            'note' => 'Kosongkan Hasil Hitung bila belum dihitung; tulis 0 bila barang memang kosong. Barang yang tidak ketemu: tulis alasannya di Catatan. Barang yang ada di rak tetapi tidak tercantum: catat di bagian bawah lembar atau lembar tambahan.',
            'signatures' => [['role' => 'Penghitung', 'name' => ''], ['role' => 'Mengetahui (Supervisor)', 'name' => '']],
        ];
    }

    /** @return array<string, mixed> */
    public function report(StockCount $count): array
    {
        $summary = $this->counts->summary($count);
        $lines = $this->counts->ordered($count);
        $diffs = $lines->filter(fn (StockCountLine $l) => $l->diff() != 0.0);
        $money = fn (float $v) => Locale::money($v, 'IDR', 0);
        $counters = $lines->pluck('counted_by')->filter()->map(fn ($n) => trim($n))->unique()->values();
        $enterers = $lines->map(fn (StockCountLine $l) => $l->enteredBy?->name)->filter()->unique()->values();
        $done = $count->status === 'done';

        $summaryRows = [
            ['k' => 'Status', 'v' => $done ? 'Selesai · disetujui '.Locale::stamp($count->approved_at).' oleh '.$count->approver?->name : 'PRATINJAU — belum disetujui (status '.$count->statusLabel().')'],
            ['k' => 'Penghitung (di kertas)', 'v' => $counters->isEmpty() ? '—' : $counters->implode(', ')],
            ['k' => 'Diinput oleh (akun)', 'v' => $enterers->isEmpty() ? '—' : $enterers->implode(', ')],
            ['k' => 'Batch dalam lembar / dihitung', 'v' => $summary['lines'].' / '.$summary['counted']],
            ['k' => 'Jumlah selisih', 'v' => $summary['diffs'].' batch ('.$summary['over_lines'].' lebih · '.$summary['under_lines'].' kurang)'],
            ['k' => 'Total nilai lebih', 'v' => $money($summary['over_value'])],
            ['k' => 'Total nilai kurang', 'v' => $money($summary['under_value'])],
            ['k' => 'Nilai bersih', 'v' => $money($summary['net_value'])],
            ['k' => 'Penyesuaian dibuat', 'v' => $count->adjustmentDocument?->doc_no ?? ($done ? 'Tidak ada (tidak ada selisih)' : '(dibuat saat disetujui)')],
        ];
        $diffRows = $diffs->values()->map(fn (StockCountLine $l, int $i) => [
            'no' => $i + 1, 'item' => ['text' => $l->item->name, 'sub' => $l->item->code.($l->item->expiry_date ? ' · exp '.Locale::d($l->item->expiry_date) : '')], 'unit' => $l->item->unit,
            'system' => $this->qty($l->system_qty), 'physical' => $this->qty($l->finalQty() ?? 0), 'diff' => $this->qty($l->diff()), 'pct' => $l->pct() === null ? '—' : Locale::number($l->pct(), 2).' %',
            'value' => $money($l->value()), 'reason' => $l->reason ?: '—', 'by' => $l->enteredBy?->name ?? '—',
        ])->all();

        return [
            'title' => 'Berita Acara Stok Opname', 'code' => $count->doc_no, 'landscape' => true,
            'info' => ['Tanggal opname: '.Locale::d($count->count_date), 'Cakupan: '.$count->scopeLabel(), 'Cut-off: '.Locale::stamp($count->cutoff_at).' · ambang selisih '.Locale::number($count->threshold_pct, 2).' %'],
            'sections' => [
                ['title' => 'Ringkasan', 'blocks' => [['type' => 'table', 'columns' => [['key' => 'k', 'label' => 'Uraian', 'width' => 70], ['key' => 'v', 'label' => 'Keterangan']], 'rows' => $summaryRows]]],
                ['title' => 'Baris selisih', 'blocks' => [['type' => 'table', 'empty' => 'Tidak ada selisih.', 'columns' => [
                    ['key' => 'no', 'label' => 'No.', 'width' => 9, 'align' => 'right'], ['key' => 'item', 'label' => 'Barang / Batch'], ['key' => 'unit', 'label' => 'Satuan', 'width' => 14],
                    ['key' => 'system', 'label' => 'Sistem', 'width' => 20, 'align' => 'right'], ['key' => 'physical', 'label' => 'Fisik', 'width' => 20, 'align' => 'right'], ['key' => 'diff', 'label' => 'Selisih', 'width' => 20, 'align' => 'right'],
                    ['key' => 'pct', 'label' => 'Persen', 'width' => 18, 'align' => 'right'], ['key' => 'value', 'label' => 'Nilai', 'width' => 28, 'align' => 'right'], ['key' => 'reason', 'label' => 'Alasan', 'width' => 50], ['key' => 'by', 'label' => 'Diinput oleh', 'width' => 30],
                ], 'rows' => $diffRows]]],
            ],
            'note' => $done ? null : 'PRATINJAU: angka dapat berubah sampai opname disetujui.',
            'signatures' => [['role' => 'Penghitung', 'name' => ''], ['role' => 'Supervisor', 'name' => $count->approver?->name ?? ''], ['role' => 'Penanggung Jawab Gudang', 'name' => '']],
        ];
    }

    private function qty(float $v): string
    {
        return rtrim(rtrim(number_format($v, 3, ',', '.'), '0'), ',');   // 10 / 10,5 / 1.234,567: tanpa nol di belakang koma
    }
}
