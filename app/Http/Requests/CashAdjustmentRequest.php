<?php

namespace App\Http\Requests;

use App\Models\CashAccount;
use App\Support\Text;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/* REMARK
 * Tujuan       : Validasi simpan Penyesuaian Kas (KSA): tanggal, kas aktif, arah (tambah / kurangi), jumlah (pengurangan tidak boleh melebihi saldo kas), alasan wajib. Aksi selain simpan / confirm tidak divalidasi di sini.
 * Dipakai oleh : CashAdjustmentController@store, @update.
 * Status       : Aktif.
 */
class CashAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        if (! in_array($this->input('_action', 'save'), ['save', 'confirm'], true)) {
            return [];
        }

        return [
            'doc_date' => ['required', 'date'],
            'cash_account_id' => ['required', Rule::exists('m_cash_account', 'id')->where('is_active', true)],
            'direction' => ['required', 'in:add,sub'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999', function ($attribute, $value, $fail) {
                $account = CashAccount::find($this->input('cash_account_id'));
                if ($account && $this->input('direction') === 'sub' && $value > $account->currentBalance()) {
                    $fail('Pengurangan melebihi saldo kas ini (saldo Rp '.number_format($account->currentBalance(), 0, ',', '.').').');
                }
            }],
            'cause' => ['required', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['cause' => Text::of($this->input('cause'))]);
    }
}
