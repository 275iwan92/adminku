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
        $rows = CashPurpose::query()->withCount('cashOuts')->orderBy('name')->get()->map(fn (CashPurpose $s) => ['id' => $s->id, 'name' => $s->name, 'used' => $s->cash_outs_count, 'active' => $s->is_active])->all();

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
        $action = $request->input('_action');
        if (in_array($action, ['activate', 'deactivate'], true)) {
            $purpose->update(['is_active' => $action === 'activate']);

            return redirect()->route('cash-purposes.show', $purpose)->with('status', $action === 'activate' ? 'Diaktifkan.' : 'Dinonaktifkan.');
        }
        if ($action === 'delete') {
            // Kategori yang sudah dipakai Uang Keluar tidak dihapus keras (grafik lama tetap utuh): dinonaktifkan, hilang dari dropdown.
            if ($purpose->cashOuts()->exists()) {
                $purpose->update(['is_active' => false]);

                return redirect()->route('cash-purposes.index')->with('status', 'Kebutuhan sudah dipakai Uang Keluar, jadi dinonaktifkan (tidak dihapus) supaya laporan lama tetap utuh.');
            }
            $purpose->delete();

            return redirect()->route('cash-purposes.index')->with('status', 'Kebutuhan dihapus.');
        }
        $purpose->update($request->validated());

        return redirect()->route('cash-purposes.show', $purpose)->with('status', 'Kebutuhan disimpan.');
    }
}
