<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Employee;
use App\Models\FacAsset;
use App\Models\FacHandover;
use App\Services\FacCare;
use App\Services\FacCustody;
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
 * Tujuan       : Serah Terima Aset (GA, modul FAH): pinjam / serah ke karyawan atau departemen, bawa pulang (izin dengan tanggal kembali), pengembalian (kondisi saat kembali, kerusakan), laporan hilang. Siklus sama dengan dokumen lain:
 *                Draf > Confirm (nomor ST lahir) > Approve oleh Supervisor (bukan pembuat) | Reject (alasan); Cancel (alasan). Approve mengubah posisi dan pemegang aset (FacCustody::apply). Satu aset satu dokumen terbuka.
 * Sumber hulu  : g_fac_handover, m_fac_asset, m_employee, m_department.
 * Output       : grid Serah Terima, form Serah Terima, foto lampiran.
 * Dipakai oleh : routes/web.php (fac-handovers.*).
 * Status       : Aktif.
 */
class FacHandoverController extends Controller
{
    public function index()
    {
        $rows = FacHandover::query()->with('asset', 'employee', 'department')->orderByDesc('id')->get()->map(fn (FacHandover $h) => [
            'id' => $h->id, 'no' => $h->number(), 'type' => FacHandover::TYPES[$h->type], 'asset' => $h->asset->asset_code.' · '.$h->asset->name, 'holder' => $h->holderText(),
            'date' => $h->doc_date->format('Y-m-d'), 'back' => $h->expected_return?->format('Y-m-d'), 'status' => FacCustody::STATUS[$h->status],
        ])->all();

        return view('fac-handovers.index', ['rows' => $rows]);
    }

    public function create(Request $request)
    {
        $h = new FacHandover(['doc_date' => today(), 'type' => array_key_exists((string) $request->query('type'), FacHandover::TYPES) ? $request->query('type') : 'loan', 'status' => 'draft']);
        if ($a = FacAsset::query()->find($request->integer('asset'))) {
            $h->asset_id = $a->id;
            if ($h->type === 'return' || $h->type === 'lost') {
                $h->employee_id = $a->holder_employee_id;
                $h->department_id = $a->holder_department_id;
            }
        }

        return view('fac-handovers.form', $this->formData($h));
    }

    public function show(FacHandover $handover)
    {
        return view('fac-handovers.form', $this->formData($handover));
    }

    public function store(Request $request): RedirectResponse
    {
        $h = FacHandover::create($this->validated($request, null) + ['status' => 'draft', 'created_by' => $request->user()->id]);
        $this->savePhoto($h, $request->file('photo'));

        return redirect()->route('fac-handovers.show', $h)->with('status', 'Draf Serah Terima disimpan. Nomor ST dibuat saat Confirm.');
    }

    public function update(Request $request, FacHandover $handover): RedirectResponse
    {
        $action = $request->input('_action', 'save');
        $user = $request->user();
        $reason = Text::of($request->input('_reason'));
        $owner = $user->id === $handover->created_by;

        $message = DB::transaction(function () use ($action, $request, $handover, $user, $reason, $owner) {
            $h = FacHandover::query()->lockForUpdate()->findOrFail($handover->id);
            $need = fn (array $from) => abort_unless(in_array($h->status, $from, true), 422, 'Status dokumen sudah berubah. Muat ulang halaman.');

            switch ($action) {
                case 'save':
                case 'confirm':
                    $need(['draft']);
                    abort_unless($owner || $user->isSupervisor(), 403, 'Hanya pembuat atau Supervisor yang mengubah draf ini.');
                    $h->update($this->validated($request, $h));
                    $this->savePhoto($h, $request->file('photo'));
                    if ($action === 'confirm') {
                        $this->assertAssetState($h);
                        $h->update(['no' => $h->no ?? FacHandover::nextNo($h->doc_date), 'status' => 'approval', 'submitted_at' => now()]);

                        return "{$h->no} dikonfirmasi dan menunggu persetujuan Supervisor.";
                    }

                    return 'Draf disimpan.';
                case 'approve':
                case 'reject':
                    $need(['approval']);
                    abort_unless($user->canApprove(), 403, 'Hanya Supervisor yang menyetujui atau menolak.');
                    abort_if($owner, 403, 'Pembuat tidak boleh menyetujui atau menolak dokumennya sendiri.');
                    abort_if($action === 'reject' && $reason === '', 422, 'Isi alasan penolakan.');
                    if ($action === 'approve') {
                        $this->assertAssetState($h);   // posisi aset bisa berubah sejak Confirm
                    }
                    $h->update(['status' => $action === 'approve' ? 'approved' : 'rejected', 'approved_by' => $user->id, 'approved_at' => now(), 'reason' => $action === 'reject' ? $reason : null]);
                    $action === 'approve' && FacCustody::apply($h);

                    return $action === 'approve' ? "{$h->no} disetujui." : "{$h->no} ditolak.";
                case 'cancel':
                    $need(['approval']);
                    abort_unless($owner || $user->isSupervisor(), 403, 'Hanya pembuat atau Supervisor yang membatalkan dokumen ini.');
                    abort_if($reason === '', 422, 'Isi alasan pembatalan.');
                    $h->update(['status' => 'cancelled', 'reason' => $reason]);

                    return "{$h->no} dibatalkan.";
                case 'delete':
                    $need(['draft']);
                    abort_unless($owner || $user->isSupervisor(), 403, 'Hanya pembuat atau Supervisor yang menghapus draf ini.');
                    $h->photo_path && Storage::disk('ga')->delete($h->photo_path);
                    $h->delete();

                    return 'Draf Serah Terima dihapus.';
                default:
                    abort(422, 'Aksi tidak dikenal.');
            }
        });

        return $action === 'delete' ? redirect()->route('fac-handovers.index')->with('status', $message) : redirect()->route('fac-handovers.show', $handover)->with('status', $message);
    }

    public function file(FacHandover $handover): StreamedResponse
    {
        abort_unless($handover->photo_path && Storage::disk('ga')->exists($handover->photo_path), 404);

        return Storage::disk('ga')->response($handover->photo_path, Str::slug($handover->number()).'.'.pathinfo($handover->photo_path, PATHINFO_EXTENSION), ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=0']);
    }

    // Posisi aset harus cocok dengan jenis dokumen: keluar hanya dari kantor, kembali hanya bila sedang keluar / diperbaiki.
    private function assertAssetState(FacHandover $h): void
    {
        $a = FacAsset::query()->findOrFail($h->asset_id);
        $err = match (true) {
            ! $a->is_active => 'Aset sudah nonaktif.',
            in_array($h->type, ['loan', 'home'], true) && $a->position !== 'office' => 'Aset tidak sedang di kantor ('.FacCustody::POSITIONS[$a->position].'). Kembalikan dulu.',
            $h->type === 'return' && ! in_array($a->position, ['loan', 'home', 'repair', 'lost'], true) => 'Aset sudah ada di kantor: tidak ada yang dikembalikan.',
            $h->type === 'lost' && $a->position === 'lost' => 'Aset sudah tercatat hilang.',
            default => null,
        };
        $err && throw ValidationException::withMessages(['asset_id' => $err]);
    }

    private function validated(Request $request, ?FacHandover $h): array
    {
        $request->merge(['employee_id' => $request->input('employee_id') ?: null, 'department_id' => $request->input('department_id') ?: null]);
        $d = $request->validate([
            'type' => ['required', 'in:'.implode(',', array_keys(FacHandover::TYPES))], 'asset_id' => ['required', 'integer', 'exists:m_fac_asset,id'],
            'employee_id' => ['nullable', 'integer', 'exists:m_employee,id'], 'department_id' => ['nullable', 'integer', 'exists:m_department,id'],
            'doc_date' => ['required', 'date'], 'expected_return' => ['nullable', 'date', 'after_or_equal:doc_date'], 'purpose' => ['nullable', 'string', 'max:255'],
            'condition_out' => ['nullable', 'in:baik,perlu,rusak'], 'damage_note' => ['nullable', 'string', 'max:255'], 'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
        ], [], ['asset_id' => 'Aset', 'doc_date' => 'Tanggal', 'expected_return' => 'Perkiraan kembali']);

        $errors = [];
        $type = $d['type'];
        if (in_array($type, ['loan', 'home'], true)) {
            if (empty($d['employee_id']) && empty($d['department_id'])) {
                $errors['employee_id'] = 'Pilih karyawan atau departemen penerima.';
            }
            if ($type === 'home' && empty($d['employee_id'])) {
                $errors['employee_id'] = 'Bawa pulang harus atas nama karyawan.';
            }
            if (empty($d['expected_return'])) {
                $errors['expected_return'] = 'Isi perkiraan tanggal kembali.';
            }
            if (empty($d['condition_out'])) {
                $errors['condition_out'] = 'Isi kondisi aset saat keluar.';
            }
        }
        if ($type === 'return' && empty($d['condition_out'])) {
            $errors['condition_out'] = 'Isi kondisi aset saat kembali.';
        }
        if ($type === 'lost' && Text::of($d['purpose'] ?? '') === '') {
            $errors['purpose'] = 'Isi kronologi hilangnya aset.';
        }
        if (FacHandover::query()->where('asset_id', $d['asset_id'])->whereIn('status', FacHandover::OPEN)->when($h, fn ($q) => $q->whereKeyNot($h->id))->exists()) {
            $errors['asset_id'] = 'Aset ini masih punya Serah Terima yang belum selesai (Draf atau Diajukan).';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        if (in_array($type, ['return', 'lost'], true)) {
            $d['expected_return'] = null;
        }
        foreach (['purpose', 'damage_note'] as $k) {
            $d[$k] = Text::of($d[$k] ?? '') ?: null;
        }
        unset($d['photo']);

        return $d;
    }

    private function savePhoto(FacHandover $h, ?UploadedFile $file): void
    {
        if ($file) {
            $old = $h->photo_path;
            $h->update(['photo_path' => $file->store("fac/handover/{$h->id}", 'ga'), 'photo_name' => mb_substr($file->getClientOriginalName(), 0, 150)]);
            $old && Storage::disk('ga')->delete($old);
        }
    }

    private function formData(FacHandover $h): array
    {
        $log = [];
        if ($h->exists) {
            $log[] = ['at' => $h->created_at->format('Y-m-d H:i:s'), 'by' => $h->creator->name, 'action' => 'Dibuat', 'note' => null];
            $h->submitted_at && $log[] = ['at' => $h->submitted_at->format('Y-m-d H:i:s'), 'by' => $h->creator->name, 'action' => 'Dikonfirmasi', 'note' => null];
            $h->approved_at && $log[] = ['at' => $h->approved_at->format('Y-m-d H:i:s'), 'by' => $h->approver?->name ?? '-', 'action' => $h->status === 'rejected' ? 'Ditolak' : 'Disetujui', 'note' => $h->status === 'rejected' ? $h->reason : null];
            $h->status === 'cancelled' && $log[] = ['at' => $h->updated_at->format('Y-m-d H:i:s'), 'by' => '-', 'action' => 'Dibatalkan', 'note' => $h->reason];
        }

        return [
            'h' => $h, 'log' => $log,
            'assets' => FacAsset::query()->where('is_active', true)->when($h->asset_id, fn ($q) => $q->orWhere('id', $h->asset_id))->orderBy('asset_code')->get()
                ->mapWithKeys(fn ($a) => [$a->id => $a->asset_code.' · '.$a->name.' ('.FacCustody::POSITIONS[$a->position].($a->holderText() ? ', '.$a->holderText() : '').')'])->all(),
            'employees' => Employee::query()->where('is_active', true)->when($h->employee_id, fn ($q) => $q->orWhere('id', $h->employee_id))->orderBy('name')->get()->mapWithKeys(fn ($e) => [$e->id => $e->code.' · '.$e->name])->all(),
            'departments' => Department::query()->where('is_active', true)->when($h->department_id, fn ($q) => $q->orWhere('id', $h->department_id))->orderBy('name')->pluck('name', 'id')->all(),
            'conditions' => ['' => '(pilih)'] + array_intersect_key(FacCare::CONDITIONS, ['baik' => 1, 'perlu' => 1, 'rusak' => 1]),
        ];
    }
}
