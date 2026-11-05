<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecipientRequest;
use App\Models\Department;
use App\Models\Recipient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

/* REMARK
 * Tujuan       : Master Penerima barang (dipilih di Issuance; dasar analisis pengeluaran per penerima). Data Entry dan Supervisor sama-sama boleh mengelola.
 * Sumber hulu  : m_recipient (+ departemen bawaan, jumlah dokumen).
 * Output       : grid Penerima, form Penerima.
 * Dipakai oleh : routes/web.php (recipients.*).
 * Status       : Aktif.
 */
class RecipientController extends Controller
{
    public function index()
    {
        $rows = Recipient::query()->with(['department', 'employee:id,code'])->withCount('documents')->orderBy('name')->get()->map(fn (Recipient $r) => [
            'id' => $r->id, 'name' => $r->name, 'department' => $r->department?->name ?? '—', 'documents' => $r->documents_count, 'active' => $r->is_active,
            'employee' => $r->employee?->code ?? '—', 'initials' => collect(preg_split('/\s+/', trim($r->name)))->filter(fn ($w) => preg_match('/^\p{L}/u', $w))->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode(''), 'hue' => abs(crc32($r->department?->name ?? '')) % 360,   // warna per departemen, sama dengan kartu Karyawan
        ])->all();

        $rows = collect($rows);

        return view('recipients.index', [
            'rows' => $rows->all(), 'view' => request()->query('view') === 'list' ? 'list' : 'cards',
            'departments' => $rows->pluck('department')->unique()->sort()->values()->all(),
            'counts' => ['all' => $rows->count(), 'off' => $rows->where('active', false)->count()],
        ]);
    }

    public function create()
    {
        return view('recipients.form', ['recipient' => new Recipient] + $this->formData());
    }

    public function show(Recipient $recipient)
    {
        return view('recipients.form', ['recipient' => $recipient->loadCount('documents')->load('employee:id,code,name')] + $this->formData());
    }

    public function store(RecipientRequest $request): RedirectResponse|JsonResponse
    {
        $recipient = Recipient::create($request->validated());
        if ($request->expectsJson()) {
            return response()->json($recipient->only(['id', 'name']), 201);
        }

        return redirect()->route('recipients.show', $recipient)->with('status', 'Penerima disimpan.');
    }

    public function update(RecipientRequest $request, Recipient $recipient): RedirectResponse
    {
        $action = $request->input('_action');
        if (in_array($action, ['activate', 'deactivate'], true)) {
            $recipient->update(['is_active' => $action === 'activate']);

            return redirect()->route('recipients.show', $recipient)->with('status', $action === 'activate' ? 'Penerima diaktifkan.' : 'Penerima dinonaktifkan.');
        }
        $recipient->update($request->validated());

        return redirect()->route('recipients.show', $recipient)->with('status', 'Penerima disimpan.');
    }

    public function toggle(Recipient $recipient): JsonResponse
    {
        $recipient->update(['is_active' => ! $recipient->is_active]);

        return response()->json(['active' => $recipient->is_active]);
    }

    private function formData(): array
    {
        return ['departments' => Department::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all()];
    }
}
