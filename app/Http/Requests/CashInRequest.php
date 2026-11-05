<?php

namespace App\Http\Requests;

use App\Models\CashAccount;
use App\Support\Text;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/* REMARK
 * Tujuan       : Validasi simpan Uang Masuk (KSM): tanggal, kas tujuan aktif, sumber dana, jumlah, lampiran. Aksi selain simpan (Konfirmasi, Setujui, Tolak, Batalkan, Hapus, Duplicate) tidak divalidasi di sini.
 * Dipakai oleh : CashInController@store, @update.
 * Status       : Aktif.
 */
class CashInRequest extends FormRequest
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
            'source_name' => ['required', 'string', 'max:150'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'description' => ['nullable', 'string', 'max:255'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['source_name' => Text::of($this->input('source_name')), 'description' => Text::of($this->input('description'))]);
        // Saklar Tunai: kas tujuan = kas tunai aktif (m_cash_account.type = cash); Rekening bank memakai pilihan rekening.
        if ($this->input('cash_kind') === 'cash') {
            $this->merge(['cash_account_id' => CashAccount::query()->where('type', 'cash')->where('is_active', true)->orderBy('id')->value('id')]);
        }
    }
}
