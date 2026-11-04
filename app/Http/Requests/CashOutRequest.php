<?php

namespace App\Http\Requests;

use App\Models\CashIn;
use App\Support\Text;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/* REMARK
 * Tujuan       : Validasi simpan Uang Keluar (KSK): tanggal, Uang Masuk tertaut (wajib sudah Disetujui), jumlah tidak boleh melebihi sisa saldo Uang Masuk itu, untuk apa, lampiran. Aksi selain simpan tidak divalidasi di sini.
 * Dipakai oleh : CashOutController@store, @update.
 * Status       : Aktif.
 */
class CashOutRequest extends FormRequest
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
            'cash_in_id' => ['required', Rule::exists('k_cash_in', 'id')->where('status', 'approved')],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999', function ($attribute, $value, $fail) {
                $cashIn = CashIn::find($this->input('cash_in_id'));
                if ($cashIn && $value > $cashIn->remainingAmount()) {
                    $fail('Jumlah melebihi sisa saldo Uang Masuk ini (sisa Rp '.number_format($cashIn->remainingAmount(), 0, ',', '.').').');
                }
            }],
            'purpose' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:255'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['purpose' => Text::of($this->input('purpose')), 'description' => Text::of($this->input('description'))]);
    }
}
