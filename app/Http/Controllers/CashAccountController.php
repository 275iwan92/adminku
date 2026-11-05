<?php

namespace App\Http\Controllers;

use App\Http\Requests\CashAccountRequest;
use App\Models\CashAccount;
use App\Support\Text;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/* REMARK
 * Tujuan       : Master Kas / Rekening (KSR, dibuka lewat tombol Setup di Uang Masuk / Uang Keluar): kas tunai dan rekening bank, saldo awal, saldo berjalan (dihitung).
 * Sumber hulu  : m_cash_account.
 * Output       : grid Kas/Rekening, form Kas/Rekening.
 * Dipakai oleh : routes/web.php (cash-accounts.*).
 * Status       : Aktif.
 */
class CashAccountController extends Controller
{
    public function index()
    {
        $rows = CashAccount::query()->orderBy('name')->get()->map(fn (CashAccount $a) => [
            'id' => $a->id, 'name' => $a->name, 'type' => $a->typeLabel(), 'account_no' => $a->account_no, 'balance' => $a->currentBalance(), 'active' => $a->is_active,
        ])->all();

        return view('cash-accounts.index', ['rows' => $rows]);
    }

    public function create()
    {
        return view('cash-accounts.form', ['account' => new CashAccount(['type' => 'cash', 'opening_balance' => 0])]);
    }

    public function show(CashAccount $account)
    {
        return view('cash-accounts.form', ['account' => $account]);
    }

    public function store(CashAccountRequest $request): RedirectResponse
    {
        $account = CashAccount::create($request->validated());

        return redirect()->route('cash-accounts.show', $account)->with('status', 'Kas / Rekening disimpan.');
    }

    public function update(CashAccountRequest $request, CashAccount $account): RedirectResponse
    {
        $action = $request->input('_action');
        if (in_array($action, ['activate', 'deactivate'], true)) {
            $account->update(['is_active' => $action === 'activate']);

            return redirect()->route('cash-accounts.show', $account)->with('status', $action === 'activate' ? 'Diaktifkan.' : 'Dinonaktifkan.');
        }
        $account->update($request->validated());

        return redirect()->route('cash-accounts.show', $account)->with('status', 'Kas / Rekening disimpan.');
    }

    public function toggle(CashAccount $account): JsonResponse
    {
        $account->update(['is_active' => ! $account->is_active]);

        return response()->json(['active' => $account->is_active]);
    }

    // Tambah cepat rekening bank dari form Uang Masuk / Uang Keluar (tombol plus): bank + no. rekening wajib, atas nama boleh kosong, saldo awal 0. Balasan JSON 201 {id,name}. Bank + no. rekening kembar ditolak.
    public function storeBank(Request $request): JsonResponse
    {
        $data = $request->validate(['bank_name' => ['required', 'string', 'max:60'], 'account_no' => ['required', 'string', 'max:30'], 'account_holder' => ['nullable', 'string', 'max:100']]);
        $data = array_map(fn ($v) => Text::of($v), $data);
        abort_if(CashAccount::query()->where('type', 'bank')->where('bank_name', $data['bank_name'])->where('account_no', $data['account_no'])->exists(), 422, 'Rekening itu sudah ada.');
        $name = $data['bank_name'].' · '.$data['account_no'].($data['account_holder'] !== '' ? ' · '.$data['account_holder'] : '');
        $account = CashAccount::create(['account_holder' => $data['account_holder'] ?: null] + $data + ['name' => mb_substr($name, 0, 100), 'type' => 'bank', 'opening_balance' => 0]);

        return response()->json($account->only(['id', 'name']), 201);
    }
}
