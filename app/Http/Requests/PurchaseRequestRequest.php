<?php

namespace App\Http\Requests;

use App\Support\Text;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/* REMARK
 * Tujuan       : Validasi simpan Permintaan Pembelian (PP): tanggal, bagian peminta, tanggal dibutuhkan, catatan, dan baris (barang master atau teks bebas, qty > 0, satuan, estimasi harga satuan). Aksi selain simpan (Konfirmasi, Setujui, Tolak, Batalkan, Hapus) tidak divalidasi di sini.
 * Dipakai oleh : PurchaseRequestController@store, @update.
 * Status       : Aktif.
 */
class PurchaseRequestRequest extends FormRequest
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
            'department_id' => ['required', Rule::exists('m_department', 'id')->where('is_active', true)],
            'need_date' => ['nullable', 'date', 'after_or_equal:doc_date'],
            'note' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['nullable', Rule::exists('m_item', 'id')->where('is_active', true)],
            'lines.*.item_name' => ['nullable', 'required_without:lines.*.item_id', 'string', 'max:150'],
            'lines.*.qty' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'lines.*.unit' => ['nullable', 'string', 'max:20', 'required_without:lines.*.item_id'],
            'lines.*.est_price' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'lines.*.note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'lines.required' => 'Tambahkan minimal satu baris barang.',
            'lines.min' => 'Tambahkan minimal satu baris barang.',
            'lines.*.item_name.required_without' => 'Pilih barang atau ketik nama barang di setiap baris.',
            'lines.*.unit.required_without' => 'Isi satuan untuk barang yang diketik.',
            'lines.*.qty.gt' => 'Qty harus lebih dari 0.',
            'need_date.after_or_equal' => 'Tanggal dibutuhkan tidak boleh sebelum tanggal PP.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['note' => Text::of($this->input('note'))]);
    }
}
