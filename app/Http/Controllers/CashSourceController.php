<?php

namespace App\Http\Controllers;

use App\Http\Requests\CashSourceRequest;
use App\Models\CashSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

/* REMARK
 * Tujuan       : Kelola Sumber Dana (daftar pilihan di Uang Masuk): lihat, tambah (juga lewat tombol plus di form, balasan JSON 201 {id,name}), ubah nama, hapus. Hak = modul Uang Masuk (KSM).
 * Sumber hulu  : m_cash_source.
 * Output       : grid Sumber Dana, form Sumber Dana.
 * Dipakai oleh : routes/web.php (cash-sources.*).
 * Status       : Aktif.
 */
class CashSourceController extends Controller
{
    public function index()
    {
        $rows = CashSource::query()->withCount('cashIns')->orderBy('name')->get()->map(fn (CashSource $s) => ['id' => $s->id, 'name' => $s->name, 'used' => $s->cash_ins_count, 'active' => $s->is_active])->all();

        return view('cash-sources.index', ['rows' => $rows]);
    }

    public function create()
    {
        return view('cash-sources.form', ['source' => new CashSource]);
    }

    public function show(CashSource $source)
    {
        return view('cash-sources.form', ['source' => $source]);
    }

    public function store(CashSourceRequest $request): RedirectResponse|JsonResponse
    {
        $source = CashSource::create($request->validated());
        if ($request->expectsJson()) {
            return response()->json($source->only(['id', 'name']), 201);
        }

        return redirect()->route('cash-sources.show', $source)->with('status', 'Sumber dana disimpan.');
    }

    public function update(CashSourceRequest $request, CashSource $source): RedirectResponse
    {
        $action = $request->input('_action');
        if (in_array($action, ['activate', 'deactivate'], true)) {
            $source->update(['is_active' => $action === 'activate']);

            return redirect()->route('cash-sources.show', $source)->with('status', $action === 'activate' ? 'Diaktifkan.' : 'Dinonaktifkan.');
        }
        if ($action === 'delete') {
            // Sumber yang sudah dipakai Uang Masuk tidak dihapus keras (laporan per sumber tetap utuh): dinonaktifkan, hilang dari dropdown.
            if ($source->cashIns()->exists()) {
                $source->update(['is_active' => false]);

                return redirect()->route('cash-sources.index')->with('status', 'Sumber dana sudah dipakai Uang Masuk, jadi dinonaktifkan (tidak dihapus) supaya laporan lama tetap utuh.');
            }
            $source->delete();

            return redirect()->route('cash-sources.index')->with('status', 'Sumber dana dihapus.');
        }
        $source->update($request->validated());

        return redirect()->route('cash-sources.show', $source)->with('status', 'Sumber dana disimpan.');
    }
}
