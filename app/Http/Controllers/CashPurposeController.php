<?php

namespace App\Http\Controllers;

use App\Http\Requests\CashPurposeRequest;
use App\Models\CashPurpose;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

/* REMARK
 * Tujuan       : Kelola Kebutuhan (daftar pilihan di Uang Keluar): lihat, tambah (juga lewat tombol plus di form, balasan JSON 201 {id,name}), ubah nama, hapus. Hak = modul Uang Keluar (KSK).
 * Sumber hulu  : m_cash_purpose.
 * Output       : grid Kebutuhan, form Kebutuhan.
 * Dipakai oleh : routes/web.php (cash-purposes.*).
 * Status       : Aktif.
 */
class CashPurposeController extends Controller
{
    public function index()
    {
        $rows = CashPurpose::query()->orderBy('name')->get()->map(fn (CashPurpose $s) => ['id' => $s->id, 'name' => $s->name, 'used' => $s->usedCount(), 'active' => true])->all();

        return view('cash-purposes.index', ['rows' => $rows]);
    }

    public function create()
    {
        return view('cash-purposes.form', ['purpose' => new CashPurpose]);
    }

    public function show(CashPurpose $purpose)
    {
        return view('cash-purposes.form', ['purpose' => $purpose]);
    }

    public function store(CashPurposeRequest $request): RedirectResponse|JsonResponse
    {
        $purpose = CashPurpose::create($request->validated());
        if ($request->expectsJson()) {
            return response()->json($purpose->only(['id', 'name']), 201);
        }

        return redirect()->route('cash-purposes.show', $purpose)->with('status', 'Kebutuhan disimpan.');
    }

    public function update(CashPurposeRequest $request, CashPurpose $purpose): RedirectResponse
    {
        if ($request->input('_action') === 'delete') {
            $purpose->delete();

            return redirect()->route('cash-purposes.index')->with('status', 'Kebutuhan dihapus. Uang Keluar lama tetap memakai namanya.');
        }
        $purpose->update($request->validated());

        return redirect()->route('cash-purposes.show', $purpose)->with('status', 'Kebutuhan disimpan.');
    }
}
