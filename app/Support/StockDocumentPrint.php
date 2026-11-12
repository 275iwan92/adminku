<?php

namespace App\Support;

use App\Enums\StockDocumentType;
use App\Models\StockDocument;
use Devlib2\PrintDoc\Document;

/* REMARK
 * Tujuan       : Cetakan dokumen stok (Saldo Awal, Penerimaan, Pengeluaran, Penyesuaian) memakai tata letak DevLib x-dl2::print-doc. Setiap cetakan membawa MODE dokumen
 *                (DRAFT / CONFIRMED / CANCELLED, penyesuaian juga WAITING APPROVAL / APPROVED / REJECTED); yang belum / tidak sah diberi tanda air.
 * Sumber hulu  : rm_stock + rm_stock_line (+ items, supplier, pembuat / penyetuju).
 * Input        : StockDocument (load()).
 * Output       : objek Document (A5 landscape sampai 5 baris, A4 portrait di atasnya).
 * Dipakai oleh : StockDocumentController@print (rute docs.print), tombol Print di docs/form.blade.php.
 * Status       : Aktif.
 */
final class StockDocumentPrint extends Document
{
    private const LABELS = [
        'Reference No.' => 'No. Referensi', 'Recipient' => 'Penerima', 'Department' => 'Departemen', 'Purpose' => 'Keperluan', 'Reason' => 'Alasan', 'Entered By' => 'Diinput oleh',
        'Supplier' => 'Pemasok', 'Expiry' => 'Kedaluwarsa', 'System' => 'Sistem', 'Counted' => 'Hitung', 'Notes' => 'Catatan', 'Cancel Reason' => 'Alasan Pembatalan',
        'Line Note' => 'Catatan Baris', 'Item Name' => 'Nama Barang',
    ];

    public string $type = 'stock-document';

    // Harga hanya untuk dokumen bernilai (Saldo Awal, Penerimaan). Zona kolom varian harus 38 mm (berharga) atau 84 mm (tanpa harga).
    public function __construct(array $data)
    {
        $kind = $data['kind'];
        $this->title = $data['title'];
        $this->priced = in_array($kind, ['OPENING', 'IN'], true);
        $this->partyLabel = $kind === 'IN' ? 'Supplier' : 'Recipient';
        $this->refs = match ($kind) {
            'IN' => [['key' => 'ref_no', 'label' => 'Reference No.'], ['key' => 'recipient', 'label' => 'Recipient'], ['key' => 'department', 'label' => 'Department'], ['key' => 'entered', 'label' => 'Entered By']],
            'OUT' => [['key' => 'department', 'label' => 'Department'], ['key' => 'reason', 'label' => 'Purpose'], ['key' => 'entered', 'label' => 'Entered By']],
            'ADJ' => [['key' => 'reason', 'label' => 'Reason'], ['key' => 'department', 'label' => 'Department'], ['key' => 'entered', 'label' => 'Entered By']],
            default => [['key' => 'entered', 'label' => 'Entered By']],
        };
        $expiry = ['key' => 'expiry', 'label' => 'Expiry', 'type' => 'date'];
        $this->extra = match ($kind) {
            'ADJ' => [['key' => 'system', 'label' => 'System', 'width' => 24, 'type' => 'money'], ['key' => 'counted', 'label' => 'Counted', 'width' => 24, 'type' => 'money'], $expiry + ['width' => 36]],
            'OUT' => [$expiry + ['width' => 36], ['key' => 'note', 'label' => 'Line Note', 'width' => 48, 'wrap' => true]],
            default => [$expiry + ['width' => 38]],
        };
        parent::__construct($data);
    }

    public static function load(StockDocument $doc, ?string $printedBy): self
    {
        $doc->loadMissing(['lines.batch', 'supplier', 'creator', 'approver', 'confirmer']);
        $t = $doc->type;
        Document::labels(self::LABELS);

        $rows = $doc->lines->map(function ($l) use ($t) {
            $qty = $l->entered_qty ?? $l->qty;
            $price = $l->entered_unit_cost ?? $l->unit_cost;

            return [
                'name' => $l->batch->name, 'sub' => $l->batch->code, 'sub2' => $t === StockDocumentType::Issuance ? null : $l->note,
                'qty' => $t === StockDocumentType::Adjustment ? $l->qty : $qty, 'unit' => $l->entered_unit ?? $l->batch->unit,
                'expiry' => $l->batch->expiry_date?->toDateString(), 'note' => $l->note,
                'system' => $l->system_qty, 'counted' => $l->counted_qty,
                'price' => $price, 'subtotal' => Decimal::amount($qty, $price),
            ];
        })->all();
        $total = Decimal::total(array_map(fn ($r) => [1, $r['subtotal']], $rows));

        $status = $doc->status === 'cancel_pending' ? ($t === StockDocumentType::Adjustment ? 'approved' : 'confirmed') : $doc->status;
        $notes = array_filter([['Notes', $doc->note], $doc->status === 'cancelled' ? ['Cancel Reason', $doc->cancel_reason] : null], fn ($n) => $n && filled($n[1]));

        return new self([
            'kind' => $t->value, 'title' => $t->label(), 'number' => $doc->doc_no, 'date' => $doc->doc_at?->toDateString(), 'status' => $status,
            'party' => ['name' => $t === StockDocumentType::Receiving ? ($doc->supplier?->name ?? '') : ($doc->recipient ?? ''), 'code' => $doc->supplier?->code, 'lines' => []],
            'ref_no' => $doc->ref_no, 'recipient' => $doc->recipient, 'department' => $doc->department, 'reason' => $doc->reason, 'entered' => $doc->creator?->name,
            'rows' => $rows, 'notes' => array_values($notes), 'printedBy' => $printedBy,
            'approvedBy' => ($doc->approver ?? $doc->confirmer)?->name,
            'sums' => ['lines' => [['Subtotal', $total]], 'grand' => $total],
        ]);
    }
}
