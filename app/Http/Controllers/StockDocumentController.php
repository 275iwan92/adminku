<?php

namespace App\Http\Controllers;

use App\Enums\StockDocumentType;
use App\Http\Requests\StockDocumentRequest;
use App\Models\Department;
use App\Models\Batch;
use App\Models\Item;
use App\Models\Recipient;
use App\Models\StockDocument;
use App\Models\Supplier;
use App\Models\Unit;
use App\Services\ItemUnits;
use App\Services\StockDocuments;
use App\Support\Decimal;
use App\Support\StockDocumentPrint;
use Devlib\Locale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/* REMARK
 * Tujuan       : Satu pengendali untuk empat jenis dokumen stok (rute docs/{type}): daftar, buat, lihat, simpan, dan siklus persetujuan penyesuaian.
 * Sumber hulu  : StockDocuments (logika), StockDocumentRequest (validasi + hak jenis), Lookup 'item_in|item_out|item_opn|item_adj'.
 * Output       : grid dokumen (docs.index) dan form dokumen (docs.form) berisi x-dl::lines.
 * Dipakai oleh : routes/web.php (docs.*).
 * Status       : Aktif.
 */
class StockDocumentController extends Controller
{
    private const STATUS = ['confirmed' => 'Terkonfirmasi', 'draft' => 'Draf', 'approval' => 'Menunggu Persetujuan', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', 'cancelled' => 'Dibatalkan', 'cancel_pending' => 'Pembatalan Menunggu Persetujuan'];

    public function __construct(private readonly StockDocuments $documents) {}

    public function index(Request $request, string $type)
    {
        $t = $this->type($request, $type);
        $sup = $request->user()->isSupervisor();

        $rows = StockDocument::query()->where('type', $t)->with(['lines.batch', 'supplier', 'creator'])->latest('doc_at')->latest('id')->limit(1000)->get()
            ->map(fn (StockDocument $d) => [
                'id' => $d->id, 'doc_no' => $d->doc_no, 'doc_at' => $d->doc_at->format('Y-m-d H:i:s'),
                'party' => $d->supplier?->name ?? $d->recipient ?? $d->reason ?? '—',
                'ref' => $d->ref_no ?? $d->department ?? ($t === StockDocumentType::Adjustment ? $d->reason : null),
                'value' => $sup ? Decimal::total($d->lines->map(fn ($l) => [abs($l->qty), $l->unit_cost ?? $l->batch->avg_cost]), 2) : null,
                'status' => self::STATUS[$d->status] ?? $d->status, 'user' => $d->creator->name, 'active' => true,
                'lines' => $d->lines->map(fn ($l) => ['name' => $l->batch->name, 'code' => $l->batch->code, 'qty' => $l->qty, 'unit' => $l->batch->unit,
                    'cost' => $sup ? ($l->unit_cost ?? $l->batch->avg_cost) : null])->all(),
            ])->all();

        return view('docs.index', ['t' => $t, 'rows' => $rows, 'sup' => $sup]);
    }

    public function create(Request $request, string $type)
    {
        return view('docs.form', $this->formData($this->type($request, $type), new StockDocument(['status' => 'new'])));
    }

    public function show(Request $request, string $type, StockDocument $document)
    {
        $t = $this->type($request, $type);
        abort_unless($document->type === $t, 404);

        return view('docs.form', $this->formData($t, $document->load(['lines.batch', 'creator', 'approver', 'confirmer', 'canceller', 'cancelRequester'])));
    }

    // Cetakan dokumen (semua mode, termasuk draf): membawa tulisan MODE dan tanda air bila belum / tidak sah.
    public function print(Request $request, string $type, StockDocument $document)
    {
        $t = $this->type($request, $type);
        abort_unless($document->type === $t, 404);

        return view('docs.print', ['doc' => StockDocumentPrint::load($document, $request->user()->name), 't' => $t, 'document' => $document]);
    }

    // Simpan = draf: belum diproses ke stok. Stok baru berubah saat Confirm (penyesuaian: saat Approve).
    public function store(StockDocumentRequest $request, string $type): RedirectResponse
    {
        $t = $request->type();
        $doc = $this->documents->create($t, $request->safe()->only(['supplier_id', 'ref_no', 'recipient_id', 'department_id', 'reason', 'note', 'doc_at']), array_values($request->validated('lines')), $request->user());

        return redirect()->route('docs.show', [$t->slug(), $doc])->with('status', "{$doc->doc_no} disimpan sebagai draf. Belum diproses ke stok sebelum Confirm.");
    }

    // Jendela "Belum ada faktor" di form Penerimaan: 1 {unit} = {factor} satuan stok, disimpan sebagai satuan masuk milik BARANG itu (bukan konversi global). Aturan siapa boleh mengubah: ItemUnits::save.
    public function conversion(Request $request, string $type)
    {
        abort_unless($this->type($request, $type) === StockDocumentType::Receiving, 404);
        $data = $request->validate([
            'item_id' => ['required', 'exists:m_item,id'],
            'unit' => ['required', 'exists:m_unit,code'],
            'factor' => ['required', 'numeric', 'gt:0', 'max:999999999'],
        ], ['factor.gt' => 'Faktor harus lebih dari 0.', 'factor.required' => 'Isi faktor satuan.']);
        ItemUnits::save(Item::findOrFail($data['item_id']), $data['unit'], $data['factor'], $request->user());

        return response()->json(['ok' => true]);
    }

    // Siklus: save (draf), confirm (diproses ke stok; penyesuaian: diajukan), approve / reject (penyesuaian), cancel (dengan alasan), delete (hanya draf).
    public function update(StockDocumentRequest $request, string $type, StockDocument $document): RedirectResponse
    {
        abort_unless($request->type() === $document->type, 404);
        $action = $request->input('_action', 'save');
        $isAdj = $document->type === StockDocumentType::Adjustment;
        $sup = $request->user()->isSupervisor();
        $me = $request->user()->id;
        abort_if(in_array($action, ['approve', 'reject'], true) && ! $sup, 403, 'Hanya Supervisor yang menyetujui atau menolak.');
        // Menarik penyesuaian yang diajukan / pengajuan pembatalan: hanya pembuat / pengajunya atau Supervisor.
        abort_if($action === 'cancel' && ! $sup && in_array($document->status, ['approval', 'cancel_pending'], true) && ! in_array($me, [$document->created_by, $document->cancel_requested_by], true), 403, 'Hanya pengaju atau Supervisor.');

        $header = $isAdj ? ['reason', 'note', 'recipient_id', 'department_id'] : ['supplier_id', 'ref_no', 'recipient_id', 'department_id', 'reason', 'note', 'doc_at'];
        if (in_array($action, ['save', 'confirm'], true)) {
            $this->documents->update($document, $request->safe()->only($header), array_values($request->validated('lines')));
        }
        $reorder = null;
        $message = match ($action) {
            'save' => 'Draf disimpan.',
            'confirm' => $isAdj ? $this->submit($document) : $this->confirm($document, $request, $reorder),
            'approve' => $document->status === 'cancel_pending' ? $this->approveCancel($document, $request) : $this->approve($document, $request),
            'reject' => $document->status === 'cancel_pending' ? $this->rejectCancel($document) : $this->reject($document, $request),
            'cancel' => $this->cancel($document, $request),
            'delete' => $this->delete($document),
            default => abort(422, 'Aksi tidak dikenal.'),
        };

        $redirect = ($action === 'delete' ? redirect()->route('docs.index', $type) : redirect()->route('docs.show', [$type, $document]))->with('status', $message);
        if ($reorder) {
            $redirect->with('reorder', collect($reorder)->map(fn (Item $p) => "{$p->code} {$p->name} (stok tersedia ".Locale::number($p->available(), 2).', titik pesan ulang '.Locale::number($p->reorder_point, 2).')')->implode('; '));
        }

        return $redirect;
    }

    private function confirm(StockDocument $document, Request $request, ?array &$reorder): string
    {
        $reorder = $this->documents->confirm($document, $request->user());

        return "{$document->doc_no} dikonfirmasi dan diposting ke stok.";
    }

    private function cancel(StockDocument $document, Request $request): string
    {
        return match ($this->documents->cancel($document, $request->user(), (string) $request->input('_reason'))) {
            'requested' => "Pembatalan {$document->doc_no} diajukan dan menunggu persetujuan Supervisor. Stok belum berubah.",
            'withdrawn' => "Pengajuan pembatalan {$document->doc_no} ditarik.",
            default => "{$document->doc_no} dibatalkan; stok dikembalikan.",
        };
    }

    private function approveCancel(StockDocument $document, Request $request): string
    {
        $this->documents->approveCancel($document, $request->user());

        return "Pembatalan {$document->doc_no} disetujui; stok dikembalikan.";
    }

    private function rejectCancel(StockDocument $document): string
    {
        $this->documents->rejectCancel($document);

        return "Pembatalan {$document->doc_no} ditolak; dokumen tetap berlaku.";
    }

    private function submit(StockDocument $document): string
    {
        $this->documents->submit($document);

        return "{$document->doc_no} dikonfirmasi dan dikirim untuk persetujuan.";
    }

    private function approve(StockDocument $document, Request $request): string
    {
        $this->documents->approve($document, $request->user());

        return "{$document->doc_no} disetujui; penyesuaian diposting ke stok.";
    }

    private function reject(StockDocument $document, Request $request): string
    {
        $this->documents->reject($document, $request->user());

        return "{$document->doc_no} ditolak; stok tidak berubah.";
    }

    private function delete(StockDocument $document): string
    {
        $this->documents->delete($document);

        return "{$document->doc_no} dihapus.";
    }

    private function type(Request $request, string $slug): StockDocumentType
    {
        $t = StockDocumentType::fromSlug($slug);
        abort_if($t->supervisorOnly() && ! $request->user()->isSupervisor(), 403, 'Hanya untuk Supervisor.');

        return $t;
    }

    private function formData(StockDocumentType $t, StockDocument $doc): array
    {
        $log = [];
        if ($doc->exists) {
            if ($doc->status === 'cancel_pending') {
                $log[] = ['at' => $doc->cancel_requested_at->format('Y-m-d H:i:s'), 'by' => $doc->cancelRequester->name, 'action' => 'Mengajukan pembatalan', 'note' => $doc->cancel_reason];
            }
            if ($doc->status === 'cancelled') {
                $reversal = StockDocument::query()->where('reversal_of_document_id', $doc->id)->value('doc_no');
                $log[] = ['at' => $doc->cancelled_at->format('Y-m-d H:i:s'), 'by' => $doc->canceller->name, 'action' => 'Dibatalkan', 'note' => $doc->cancel_reason.($reversal ? " (dokumen pembalik otomatis: {$reversal})" : '')];
            }
            if (in_array($doc->status, ['approved', 'rejected'], true) || ($doc->status === 'cancelled' && $doc->approved_at)) {
                $log[] = ['at' => $doc->approved_at->format('Y-m-d H:i:s'), 'by' => $doc->approver->name, 'action' => self::STATUS[$doc->status === 'cancelled' ? 'approved' : $doc->status], 'note' => null];
            }
            if ($doc->confirmed_at) {
                $log[] = ['at' => $doc->confirmed_at->format('Y-m-d H:i:s'), 'by' => $doc->confirmer->name, 'action' => 'Dikonfirmasi (diproses ke stok)', 'note' => null];
            }
            $log[] = ['at' => $doc->created_at->format('Y-m-d H:i:s'), 'by' => $doc->creator->name, 'action' => 'Dibuat', 'note' => null];
        }

        return [
            't' => $t, 'doc' => $doc, 'log' => $log, 'cancelProblem' => $doc->exists ? $this->documents->cancelProblem($doc) : null,
            'recipients' => Recipient::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all(),
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all(),
            'suppliers' => Supplier::query()->where('is_active', true)->forSelect()->pluck('name', 'id')->all(),
            'units' => Unit::query()->where('is_active', true)->orderBy('code')->get()->mapWithKeys(fn (Unit $u) => [$u->code => "{$u->code} · {$u->name}"])->all(),
            'lines' => $this->lineRows($doc, $t),
            'fefo' => $doc->exists && $doc->status === 'draft' ? $this->documents->fefoWarnings($doc) : [],
        ];
    }

    // Baris tabel item: dari input lama (validasi gagal) atau dari dokumen. Kolom pilihan item butuh label teksnya (batch_id_label).
    private function lineRows(StockDocument $doc, StockDocumentType $t): array
    {
        $byProduct = in_array($t, [StockDocumentType::Receiving, StockDocumentType::Opening], true);
        $old = old('lines');
        if (is_array($old)) {
            $items = Batch::query()->whereIn('id', array_filter(array_column($old, 'batch_id')))->get()->keyBy('id');
            $products = Item::query()->whereIn('id', array_filter(array_column($old, 'item_id')))->get()->keyBy('id');

            return array_map(fn ($r) => $this->lineRow($r, $items[$r['batch_id'] ?? 0] ?? null, $products[$r['item_id'] ?? 0] ?? null, $t === StockDocumentType::Receiving), array_values($old));
        }

        // Dokumen baru: baris pertama sudah berisi jumlah bawaan 1 (bukan kosong); penyesuaian mengisi jumlah hitung sendiri.
        if (! $doc->exists) {
            return $t === StockDocumentType::Adjustment ? [] : [$this->lineRow(['qty' => 1], null)];
        }

        // Hanya Penerimaan yang menyimpan satuan ketikan; jenis lain selalu satuan stok (dokumen lama yang punya entered_* tetap terbaca benar lewat qty).
        $entered = $t === StockDocumentType::Receiving;

        return $doc->exists ? $doc->lines->map(fn ($l) => $this->lineRow([
            ($byProduct ? 'item_id' : 'batch_id') => $byProduct ? $l->batch->item_id : $l->batch_id,
            'expiry_date' => $byProduct ? $l->batch->expiry_date?->format('Y-m-d') : null, 'qty' => $entered ? $l->entered_qty ?? $l->qty : $l->qty, 'unit' => $entered ? $l->entered_unit ?? $l->batch->unit : $l->batch->unit, 'unit_cost' => $l->entered_unit_cost ?? $l->unit_cost,
            'stock_qty' => $l->qty, 'system_qty' => $l->system_qty, 'counted_qty' => $l->counted_qty, 'note' => $l->note,
        ], $l->batch, $byProduct ? $l->batch->item : null, $entered))->all() : [];
    }

    // $packs (Penerimaan): satuan stok + pilihan satuan masuk barang itu, dibaca form untuk menawarkan satuan dan menghitung hasil konversi.
    private function lineRow(array $row, ?Batch $item, ?Item $product = null, bool $packs = false): array
    {
        $own = $product ?? $item?->item;

        return ($packs && $own ? ['stock_unit' => $own->unit, 'packs' => json_encode(ItemUnits::options($own))] : []) + $row + [
            'batch_id_label' => $item?->label() ?? '',
            'item_id_label' => $product ? "{$product->code} · {$product->name}" : '',
            'unit' => $item?->unit, 'available' => $item?->stock_qty, 'system_qty' => $item?->stock_qty,
        ];
    }
}
