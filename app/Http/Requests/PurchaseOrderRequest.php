<?php

namespace App\Http\Requests;

use App\Support\Text;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/* REMARK
 * Tujuan       : Validasi simpan Pesanan Pembelian (PO): tanggal, pemasok aktif, syarat bayar, tanggal kirim, kirim ke, PPN opsional, catatan, dan baris (barang master atau teks bebas, qty > 0, satuan, harga satuan >= 0). Aksi selain Simpan / Ajukan tidak divalidasi di sini.
 * Dipakai oleh : PurchaseOrderController@store, @update.
 * Status       : Aktif.
 */
class PurchaseOrderRequest extends FormRequest
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
            'supplier_id' => ['required', Rule::exists('m_supplier', 'id')->where('is_active', true)],
            'payment_term_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'need_date' => ['nullable', 'date', 'after_or_equal:doc_date'],
            'ship_to' => ['nullable', 'string', 'max:150'],
            'vat_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'note' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['nullable', Rule::exists('m_item', 'id')->where('is_active', true)],
            'lines.*.item_name' => ['nullable', 'required_without:lines.*.item_id', 'string', 'max:150'],
            'lines.*.qty' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'lines.*.unit' => ['nullable', 'string', 'max:20', 'required_without:lines.*.item_id'],
            'lines.*.price' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'lines.*.note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'supplier_id.required' => 'Pilih pemasok.',
            'lines.required' => 'Tambahkan minimal satu baris barang.',
            'lines.min' => 'Tambahkan minimal satu baris barang.',
            'lines.*.item_name.required_without' => 'Pilih barang atau ketik nama barang di setiap baris.',
            'lines.*.unit.required_without' => 'Isi satuan untuk barang yang diketik.',
            'lines.*.qty.gt' => 'Qty harus lebih dari 0.',
            'lines.*.price.required' => 'Isi harga satuan di setiap baris.',
            'need_date.after_or_equal' => 'Tanggal kirim tidak boleh sebelum tanggal PO.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['note' => Text::of($this->input('note')), 'ship_to' => Text::of($this->input('ship_to'))]);
    }
}
