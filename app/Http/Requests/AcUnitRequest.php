<?php

namespace App\Http\Requests;

use App\Support\Text;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/* REMARK
 * Tujuan       : Validasi simpan master Unit AC: kode aset (unik, kosong = otomatis AC-nnnn, TIDAK berubah setelah disimpan), ruang (aktif), merek, tipe, PK, tanggal pasang, posisi titik di denah (persen),
 *                cuci rutin dan servis besar terakhir sebelum tercatat di sistem, catatan.
 * Dipakai oleh : AcUnitController@store, @update.
 * Status       : Aktif.
 */
class AcUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;   // hak diatur modul GAK (Grup), dijaga EnsureModuleAccess
    }

    public function rules(): array
    {
        if (in_array($this->input('_action'), ['activate', 'deactivate'], true)) {
            return [];
        }
        $own = $this->route('unit');

        return [
            'asset_code' => $own ? ['prohibited'] : ['nullable', 'string', 'max:20', Rule::unique('m_ac_unit', 'asset_code')],
            'room_id' => ['required', Rule::exists('m_ac_room', 'id')->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $own?->room_id))],
            'brand' => ['required', 'string', 'max:60'],
            'model' => ['nullable', 'string', 'max:80'],
            'capacity_pk' => ['required', 'numeric', 'min:0.5', 'max:99'],
            'installed_at' => ['nullable', 'date', 'before_or_equal:today'],
            'pos_x' => ['nullable', 'required_with:pos_y', 'numeric', 'min:0', 'max:100'],
            'pos_y' => ['nullable', 'required_with:pos_x', 'numeric', 'min:0', 'max:100'],
            'last_wash_at' => ['nullable', 'date', 'before_or_equal:today'],
            'last_major_at' => ['nullable', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'asset_code.prohibited' => 'Kode aset tidak bisa diubah: stikernya sudah menempel di unit. Pindahkan AC dengan mengganti ruangnya.',
            'asset_code.unique' => 'Kode aset ini sudah dipakai unit AC lain.',
            'installed_at.before_or_equal' => 'Tanggal pasang tidak boleh setelah hari ini.',
            'last_wash_at.before_or_equal' => 'Tanggal cuci terakhir tidak boleh setelah hari ini.',
            'last_major_at.before_or_equal' => 'Tanggal servis besar terakhir tidak boleh setelah hari ini.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $in = fn (string $k) => Text::of($this->input($k)) ?: null;
        $this->merge([
            'asset_code' => strtoupper(Text::of($this->input('asset_code'))) ?: null, 'room_id' => Text::of($this->input('room_id')), 'brand' => Text::of($this->input('brand')), 'model' => $in('model'),
            'capacity_pk' => Text::of($this->input('capacity_pk')), 'installed_at' => $in('installed_at'), 'pos_x' => $in('pos_x'), 'pos_y' => $in('pos_y'),
            'last_wash_at' => $in('last_wash_at'), 'last_major_at' => $in('last_major_at'), 'note' => $in('note'),
        ]);
    }
}
