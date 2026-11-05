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
        $rows = CashSource::query()->orderBy('name')->get()->map(fn (CashSource $s) => ['id' => $s->id, 'name' => $s->name, 'used' => $s->usedCount(), 'active' => true])->all();

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
        if ($request->input('_action') === 'delete') {
            $source->delete();

            return redirect()->route('cash-sources.index')->with('status', 'Sumber dana dihapus. Uang Masuk lama tetap memakai namanya.');
        }
        $source->update($request->validated());

        return redirect()->route('cash-sources.show', $source)->with('status', 'Sumber dana disimpan.');
    }
}
