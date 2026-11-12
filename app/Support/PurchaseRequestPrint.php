<?php

namespace App\Support;

use App\Models\PurchaseRequest;
use Devlib2\PrintDoc\Document;

/* REMARK
 * Tujuan       : Cetakan Permintaan Pembelian (PP): departemen, tanggal butuh, baris barang + jumlah sudah dipesan. Tanpa harga. Tanda tangan Pembuat / Penyetuju.
 * Sumber hulu  : p_request + p_request_line (+ m_item, m_department, pembuat / penyetuju).
 * Dipakai oleh : PurchaseRequestController@print (rute pr.print), tombol Print di purchase-requests/form.blade.php.
 * Status       : Aktif.
 */
final class PurchaseRequestPrint extends Document
{
    private const LABELS = ['Purchase Request' => 'Permintaan Pembelian', 'Department' => 'Departemen', 'Need Date' => 'Tanggal Butuh', 'Ordered' => 'Dipesan', 'Notes' => 'Catatan'];

    public string $type = 'purchase-request';

    public string $title = 'Purchase Request';

    public string $partyLabel = 'Department';

    public array $refs = [['key' => 'need_date', 'label' => 'Need Date', 'type' => 'date']];

    public array $extra = [['key' => 'ordered', 'label' => 'Ordered', 'width' => 30], ['key' => 'rnote', 'label' => 'Notes', 'width' => 54]];

    public static function load(PurchaseRequest $pr, ?string $printedBy): self
    {
        $pr->loadMissing(['lines.item', 'department', 'creator', 'approver']);
        Document::labels(self::LABELS);

        return new self([
            'kind' => 'PP', 'number' => $pr->doc_no, 'date' => $pr->doc_date->toDateString(), 'status' => 'confirmed',
            'party' => ['name' => $pr->department?->name ?? '-', 'code' => $pr->department?->code, 'lines' => []],
            'need_date' => $pr->need_date?->toDateString(),
            'rows' => $pr->lines->map(fn ($l) => [
                'name' => $l->item?->name ?? $l->item_name, 'sub' => $l->item?->code, 'qty' => $l->qty, 'unit' => $l->unit, 'ordered' => $l->ordered_qty, 'rnote' => $l->note,
            ])->all(),
            'notes' => array_values(array_filter([filled($pr->note) ? ['Notes', $pr->note] : null])), 'printedBy' => $printedBy, 'approvedBy' => $pr->approver?->name,
        ]);
    }
}
