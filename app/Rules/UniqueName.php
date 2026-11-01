<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

/* REMARK
 * Tujuan       : Nama master unik tanpa membedakan huruf besar / kecil dan tanpa spasi di ujung ("Maintenance" = "maintenance " = "MAINTENANCE"), sama di form penuh dan di tambah-cepat.
 * Input        : tabel master, id baris yang sedang diubah (dikecualikan), kolom (bawaan name).
 * Dipakai oleh : SupplierRequest, DepartmentRequest, CategoryRequest, LocationRequest, UnitRequest, RecipientRequest. Nama Barang SENGAJA tidak unik (merek berbeda boleh sama nama).
 * Status       : Aktif.
 */
final class UniqueName implements ValidationRule
{
    public function __construct(private readonly string $table, private readonly mixed $ignore = null, private readonly string $column = 'name') {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }
        $id = is_object($this->ignore) ? $this->ignore->getKey() : $this->ignore;
        $taken = DB::table($this->table)->whereRaw("lower(trim({$this->column})) = ?", [mb_strtolower(trim($value))])
            ->when($id, fn ($q) => $q->where('id', '!=', $id))->exists();
        if ($taken) {
            $fail('validation.unique')->translate();
        }
    }
}
