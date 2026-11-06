<?php

namespace App\Http\Controllers;

use App\Models\AcVendor;
use App\Models\FacAsset;
use App\Models\FacOrder;
use App\Models\FacPlace;
use App\Models\FacPlan;
use App\Services\FacCare;
use App\Support\Text;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/* REMARK
 * Tujuan       : Perintah Kerja / tiket perawatan (GA, modul FAT): dibuat dari laporan kerusakan (aset atau gedung/ruang) atau dari jadwal berkala. Siklus sama dengan PP: Draf (boleh diubah pembuat / Supervisor) > Confirm (nomor WO lahir, wajib
 *                tanggal selesai dan penanggung pekerjaan) > Approve oleh Supervisor (bukan pembuat) = Selesai | Reject (alasan); Cancel (alasan). Biaya = tenaga + material; foto sebelum / sesudah di disk privat "ga".
 *                Approve menutup jadwal asalnya (last_done_at) dan mengembalikan kondisi aset ke Baik bila tak ada tiket terbuka lain.
 * Sumber hulu  : g_fac_order, m_fac_asset, m_fac_place, g_fac_plan, m_ac_vendor.
 * Output       : grid Tiket, form Perintah Kerja.
 * Dipakai oleh : routes/web.php (fac-orders.*).
 * Status       : Aktif.
 */
class FacOrderController extends Controller
{
    public function index()
    {
        $rows = FacOrder::query()->with('asset', 'place.parent', 'vendor')->orderByDesc('id')->get()->map(fn (FacOrder $o) => [
            'id' => $o->id, 'no' => $o->number(), 'title' => $o->title, 'target' => $o->targetText(), 'priority' => FacCare::PRIORITIES[$o->priority], 'status' => FacCare::STATUS[$o->status],
            'handler' => $o->handler === 'vendor' ? ($o->vendor?->name ?? 'Vendor') : ($o->technician ?: 'Internal'), 'date' => $o->reported_at->format('Y-m-d'), 'total' => $o->total(),
        ])->all();

        return view('fac-orders.index', ['rows' => $rows]);
    }

    public function create(Request $request)
    {
        $order = new FacOrder(['reported_at' => today(), 'priority' => 'normal', 'handler' => 'vendor', 'status' => 'draft']);
        if ($plan = FacPlan::query()->find($request->integer('plan'))) {
            $order->fill(['plan_id' => $plan->id, 'asset_id' => $plan->asset_id, 'place_id' => $plan->place_id, 'title' => $plan->title, 'vendor_id' => $plan->vendor_id, 'description' => 'Perawatan berkala ('.FacCare::PERIODS[$plan->period].')']);
        } elseif ($asset = FacAsset::query()->find($request->integer('asset'))) {
            $order->fill(['asset_id' => $asset->id, 'place_id' => $asset->place_id]);
        }

        return view('fac-orders.form', $this->formData($order));
    }

    public function show(FacOrder $order)
    {
        return view('fac-orders.form', $this->formData($order));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, false);
        $order = FacOrder::create($data + ['status' => 'draft', 'created_by' => $request->user()->id]);
        $this->savePhotos($order, $request);

        return redirect()->route('fac-orders.show', $order)->with('status', 'Draf Perintah Kerja disimpan. Nomor WO dibuat saat Confirm.');
    }

    // Siklus: save, confirm (draf -> diajukan), approve / reject (Supervisor bukan pembuat), cancel (alasan), delete (draf).
    public function update(Request $request, FacOrder $order): RedirectResponse
    {
        $action = $request->input('_action', 'save');
        $user = $request->user();
        $reason = Text::of($request->input('_reason'));
        $owner = $user->id === $order->created_by;

        $message = DB::transaction(function () use ($action, $request, $order, $user, $reason, $owner) {
            $o = FacOrder::query()->lockForUpdate()->findOrFail($order->id);   // status dibaca ulang di dalam kunci: dua klik tidak melompati siklus
            $need = fn (array $from) => abort_unless(in_array($o->status, $from, true), 422, 'Status Perintah Kerja sudah berubah. Muat ulang halaman.');

            switch ($action) {
                case 'save':
                case 'confirm':
                    $need(['draft']);
                    abort_unless($owner || $user->isSupervisor(), 403, 'Hanya pembuat atau Supervisor yang mengubah draf ini.');
                    $o->update($this->validated($request, $action === 'confirm'));
                    $this->savePhotos($o, $request);
                    if ($action === 'confirm') {
                        $o->update(['no' => $o->no ?? FacOrder::nextNo($o->reported_at), 'status' => 'approval', 'submitted_at' => now()]);

                        return "{$o->no} dikonfirmasi dan menunggu persetujuan Supervisor.";
                    }

                    return 'Draf disimpan.';
                case 'approve':
                case 'reject':
                    $need(['approval']);
                    abort_unless($user->canApprove(), 403, 'Hanya Supervisor yang menyetujui atau menolak.');
                    abort_if($owner, 403, 'Pembuat tidak boleh menyetujui atau menolak Perintah Kerjanya sendiri.');
                    abort_if($action === 'reject' && $reason === '', 422, 'Isi alasan penolakan.');
                    $o->update(['status' => $action === 'approve' ? 'approved' : 'rejected', 'approved_by' => $user->id, 'approved_at' => now(), 'reason' => $action === 'reject' ? $reason : null]);
                    $action === 'approve' && FacCare::onApproved($o);

                    return $action === 'approve' ? "{$o->no} disetujui: pekerjaan Selesai." : "{$o->no} ditolak.";
                case 'cancel':
                    $need(['approval']);
                    abort_unless($owner || $user->isSupervisor(), 403, 'Hanya pembuat atau Supervisor yang membatalkan Perintah Kerja ini.');
                    abort_if($reason === '', 422, 'Isi alasan pembatalan.');
                    $o->update(['status' => 'cancelled', 'reason' => $reason]);

                    return "{$o->no} dibatalkan.";
                case 'delete':
                    $need(['draft']);
                    abort_unless($owner || $user->isSupervisor(), 403, 'Hanya pembuat atau Supervisor yang menghapus draf ini.');
                    foreach (['before', 'after'] as $k) {
                        $o->{"photo_{$k}_path"} && Storage::disk('ga')->delete($o->{"photo_{$k}_path"});
                    }
                    $o->delete();

                    return 'Draf Perintah Kerja dihapus.';
                default:
                    abort(422, 'Aksi tidak dikenal.');
            }
        });

        return $action === 'delete' ? redirect()->route('fac-orders.index')->with('status', $message) : redirect()->route('fac-orders.show', $order)->with('status', $message);
    }

    // Foto sebelum / sesudah (privat di disk "ga"): hanya yang punya hak modul FAT.
    public function file(FacOrder $order, string $kind): StreamedResponse
    {
        abort_unless(in_array($kind, ['before', 'after'], true), 404);
        $path = $order->{"photo_{$kind}_path"};
        abort_unless($path && Storage::disk('ga')->exists($path), 404);

        return Storage::disk('ga')->response($path, Str::slug($order->number().' '.$kind).'.'.pathinfo($path, PATHINFO_EXTENSION), ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=0']);
    }

    private function validated(Request $request, bool $confirm): array
    {
        $money = ['nullable', 'numeric', 'min:0', 'max:999999999999'];
        $d = $request->validate([
            'title' => ['required', 'string', 'max:150'], 'description' => ['nullable', 'string', 'max:2000'],
            'asset_id' => ['nullable', 'integer', 'exists:m_fac_asset,id'], 'place_id' => ['nullable', 'integer', 'exists:m_fac_place,id'], 'plan_id' => ['nullable', 'integer', 'exists:g_fac_plan,id'],
            'priority' => ['required', 'in:'.implode(',', array_keys(FacCare::PRIORITIES))], 'reported_at' => ['required', 'date'],
            'handler' => ['required', 'in:vendor,internal'], 'vendor_id' => ['nullable', 'integer', 'exists:m_ac_vendor,id'], 'technician' => ['nullable', 'string', 'max:100'],
            'labor_cost' => $money, 'material_cost' => $money, 'finished_at' => [$confirm ? 'required' : 'nullable', 'date', 'after_or_equal:reported_at'], 'note' => ['nullable', 'string', 'max:255'],
            'photo_before' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:5120'], 'photo_after' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
        ], [], ['title' => 'Judul', 'finished_at' => 'Tanggal selesai', 'reported_at' => 'Tanggal lapor']);

        $errors = [];
        if (empty($d['asset_id']) && empty($d['place_id'])) {
            $errors['asset_id'] = 'Pilih aset, atau gedung / ruang bila kerusakannya bukan pada satu aset.';
        }
        if ($confirm && $d['handler'] === 'vendor' && empty($d['vendor_id'])) {
            $errors['vendor_id'] = 'Pilih vendor yang mengerjakan.';
        }
        if ($confirm && $d['handler'] === 'internal' && Text::of($d['technician'] ?? '') === '') {
            $errors['technician'] = 'Isi nama teknisi internal.';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        if (! empty($d['asset_id'])) {
            $d['place_id'] = FacAsset::query()->whereKey($d['asset_id'])->value('place_id') ?? ($d['place_id'] ?? null);
        }
        $d['vendor_id'] = $d['handler'] === 'vendor' ? ($d['vendor_id'] ?? null) : null;
        $d['technician'] = $d['handler'] === 'internal' ? (Text::of($d['technician'] ?? '') ?: null) : null;
        $d['labor_cost'] = $d['labor_cost'] ?? 0;
        $d['material_cost'] = $d['material_cost'] ?? 0;
        foreach (['title', 'description', 'note'] as $k) {
            $d[$k] = Text::of($d[$k] ?? '') ?: null;
        }
        unset($d['photo_before'], $d['photo_after']);

        return $d;
    }

    private function savePhotos(FacOrder $o, Request $request): void
    {
        foreach (['before', 'after'] as $k) {
            $file = $request->file("photo_{$k}");
            if (! $file instanceof UploadedFile) {
                continue;
            }
            $old = $o->{"photo_{$k}_path"};
            $o->update(["photo_{$k}_path" => $file->store("fac/order/{$o->id}", 'ga'), "photo_{$k}_name" => mb_substr($file->getClientOriginalName(), 0, 150)]);
            $old && Storage::disk('ga')->delete($old);
        }
    }

    private function formData(FacOrder $o): array
    {
        $log = [];
        if ($o->exists) {
            $log[] = ['at' => $o->created_at->format('Y-m-d H:i:s'), 'by' => $o->creator->name, 'action' => 'Dibuat', 'note' => null];
            $o->submitted_at && $log[] = ['at' => $o->submitted_at->format('Y-m-d H:i:s'), 'by' => $o->creator->name, 'action' => 'Dikonfirmasi', 'note' => null];
            $o->approved_at && $log[] = ['at' => $o->approved_at->format('Y-m-d H:i:s'), 'by' => $o->approver?->name ?? '-', 'action' => $o->status === 'rejected' ? 'Ditolak' : 'Disetujui (Selesai)', 'note' => $o->status === 'rejected' ? $o->reason : null];
            $o->status === 'cancelled' && $log[] = ['at' => $o->updated_at->format('Y-m-d H:i:s'), 'by' => '-', 'action' => 'Dibatalkan', 'note' => $o->reason];
        }

        return [
            'order' => $o, 'log' => $log,
            'assets' => FacAsset::query()->where('is_active', true)->when($o->asset_id, fn ($q) => $q->orWhere('id', $o->asset_id))->orderBy('asset_code')->get()->mapWithKeys(fn ($a) => [$a->id => $a->asset_code.' · '.$a->name])->all(),
            'places' => FacPlace::query()->with('parent')->where('is_active', true)->when($o->place_id, fn ($q) => $q->orWhere('id', $o->place_id))->get()->sortBy(fn ($p) => $p->path())->mapWithKeys(fn ($p) => [$p->id => $p->path()])->all(),
            'vendors' => AcVendor::query()->where('is_active', true)->when($o->vendor_id, fn ($q) => $q->orWhere('id', $o->vendor_id))->orderBy('name')->pluck('name', 'id')->all(),
        ];
    }
}
