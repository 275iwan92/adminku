<?php

namespace App\Http\Requests;

use App\Models\CashAccount;
use App\Models\CashIn;
use App\Models\CashPurpose;
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
            // Tautan Uang Masuk opsional; bila dipilih, Kas Asal dikunci mengikuti kas Uang Masuk itu (prepareForValidation).
            'cash_in_id' => ['nullable', Rule::exists('k_cash_in', 'id')->where('status', 'approved')],
            'cash_account_id' => ['required', Rule::exists('m_cash_account', 'id')->where('is_active', true)],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999', function ($attribute, $value, $fail) {
                $cashIn = CashIn::find($this->input('cash_in_id'));
                if ($cashIn && $value > $cashIn->remainingAmount()) {
                    $fail('Jumlah melebihi sisa saldo Uang Masuk ini (sisa Rp '.number_format($cashIn->remainingAmount(), 0, ',', '.').').');
                }
                $account = CashAccount::find($this->input('cash_account_id'));
                if ($account && $value > $account->currentBalance()) {
                    $fail('Jumlah melebihi saldo kas ini (saldo Rp '.number_format($account->currentBalance(), 0, ',', '.').').');
                }
            }],
            'purpose' => ['required', 'string', 'max:150'],
            'purpose_id' => ['nullable', 'integer'],
            // Kategori "Lainnya" tidak menjelaskan apa pun: rincian wajib ditulis di Keterangan.
            'description' => ['nullable', 'string', 'max:255', Rule::requiredIf(fn () => $this->input('purpose') === 'Lainnya')],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Kas Asal: tautan Uang Masuk mengunci kasnya; tanpa tautan, saklar Tunai memakai kas tunai aktif, Rekening bank memakai rekening yang dipilih.
        $link = $this->filled('cash_in_id') ? CashIn::query()->where('status', 'approved')->find($this->input('cash_in_id')) : null;
        if ($link) {
            $this->merge(['cash_account_id' => $link->cash_account_id]);
        } elseif ($this->input('cash_kind') === 'cash') {
            $this->merge(['cash_account_id' => CashAccount::query()->where('type', 'cash')->where('is_active', true)->orderBy('id')->value('id')]);
        }
        $purpose = Text::of($this->input('purpose'));
        $this->merge(['purpose' => $purpose, 'purpose_id' => CashPurpose::query()->where('name', $purpose)->value('id'), 'description' => Text::of($this->input('description'))]);
    }
}
