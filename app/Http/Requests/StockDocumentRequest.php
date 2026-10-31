<?php

namespace App\Http\Requests;

use App\Enums\StockDocumentType;
use App\Models\Product;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/* REMARK
 * Tujuan       : Validasi simpan dokumen stok menurut jenisnya (Receiving, Issuance, Opening Balance, Stock Adjustment) + hak akses jenis itu.
 * Sumber hulu  : parameter rute {type} (slug), peran user (Opening / Adjustment hanya Supervisor).
 * Dipakai oleh : StockDocumentController@store, @update. Aksi approve / reject / delete tidak mengirim isian, jadi tanpa aturan.
 * Status       : Aktif.
 */
class StockDocumentRequest extends FormRequest
{
    public function type(): StockDocumentType
    {
        return StockDocumentType::fromSlug($this->route('type'));
    }

    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && (! $this->type()->supervisorOnly() || $user->isSupervisor());
    }

    public function rules(): array
    {
        if (in_array($this->input('_action'), ['approve', 'reject', 'delete', 'cancel'], true)) {
            return [];
        }

        $recipient = ['nullable', Rule::exists('m_recipient', 'id')->where('is_active', true)];
        $department = ['nullable', Rule::exists('m_department', 'id')->where('is_active', true)];
        $qty = ['required', 'numeric', 'gt:0', 'max:999999999'];
        $common = [
            'note' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.note' => ['nullable', 'string', 'max:255'],
        ];

        // Penerimaan / Saldo Awal: produk + tanggal kedaluwarsa (batch dibuat otomatis); batch langsung (item_id) tetap diterima untuk pemakaian lewat kode.
        $byProduct = [
            'lines.*.product_id' => ['nullable', 'required_without:lines.*.item_id', Rule::exists('m_item', 'id')->where('is_active', true)],
            'lines.*.item_id' => ['nullable', 'required_without:lines.*.product_id', Rule::exists('items', 'id')->where('is_active', true)],
            'lines.*.expiry_date' => ['nullable', 'date'],
        ];
        // Pengeluaran / Penyesuaian: batch (barang) yang dipilih.
        $byItem = ['lines.*.item_id' => ['required', 'distinct', Rule::exists('items', 'id')->where('is_active', true)]];

        return $common + match ($this->type()) {
            StockDocumentType::Receiving => [
                'supplier_id' => ['required', Rule::exists('m_supplier', 'id')->where('is_active', true)],
                'ref_no' => ['nullable', 'string', 'max:60'],
                'department_id' => $department,
                'recipient_id' => $recipient,
                'lines.*.qty' => $qty,
                'lines.*.unit' => ['nullable', Rule::exists('m_unit', 'code')->where('is_active', true)],
                'lines.*.unit_cost' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            ] + $byProduct,
            StockDocumentType::Issuance => [
                'recipient_id' => ['required', Rule::exists('m_recipient', 'id')->where('is_active', true)],
                'department_id' => $department,
                'reason' => ['nullable', 'string', 'max:255'],
                'lines.*.qty' => $qty,
            ] + $byItem,
            StockDocumentType::Opening => [
                'doc_at' => ['required', 'date', 'before_or_equal:today'],
                'lines.*.qty' => $qty,
                'lines.*.unit_cost' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            ] + $byProduct,
            StockDocumentType::Adjustment => [
                'department_id' => $department,
                'recipient_id' => $recipient,
                'reason' => ['required', 'string', 'max:255'],
                'lines.*.counted_qty' => ['required', 'numeric', 'min:0', 'max:999999999'],
            ] + $byItem,
        };
    }

    // Produk berkedaluwarsa wajib bertanggal di baris Penerimaan / Saldo Awal.
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            foreach ((array) $this->input('lines', []) as $i => $row) {
                $product = ! empty($row['product_id']) ? Product::find($row['product_id']) : null;
                if ($product?->tracks_expiry && empty($row['expiry_date'])) {
                    $v->errors()->add("lines.{$i}.expiry_date", "Isi tanggal kedaluwarsa untuk {$product->name}.");
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'lines.required' => 'Tambahkan minimal satu baris barang.',
            'lines.min' => 'Tambahkan minimal satu baris barang.',
            'lines.*.item_id.required' => 'Pilih barang di setiap baris.',
            'lines.*.product_id.required_without' => 'Pilih barang di setiap baris.',
            'lines.*.item_id.required_without' => 'Pilih barang di setiap baris.',
            'lines.*.product_id.exists' => 'Barang yang dipilih tidak ada atau nonaktif.',
            'lines.*.item_id.distinct' => 'Barang yang sama muncul di lebih dari satu baris.',
            'lines.*.item_id.exists' => 'Barang yang dipilih tidak ada atau nonaktif.',
            'lines.*.qty.gt' => 'Jumlah harus lebih dari 0.',
        ];
    }
}
