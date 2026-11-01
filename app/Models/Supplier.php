<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Database\Eloquent\Model;
use LogicException;

// is_system = supplier bawaan (Kontan): tak bisa dinonaktifkan, diganti nama / kodenya, atau dihapus; selalu pertama di dropdown. Hanya diisi migrasi / seeder (tidak mass-assignable).
// phone = telepon kantor (PSTN); contact_mobile = HP kontak; address = nama jalan; area_level + area_id = wilayah (lihat App\Support\GeoArea).
#[Fillable(['code', 'name', 'phone', 'contact_name', 'contact_mobile', 'address', 'area_level', 'area_id', 'postal_code', 'is_active'])]
class Supplier extends Model
{
    protected $table = 'm_supplier';

    public const SYSTEM_MESSAGE = 'Kontan adalah supplier sistem: tidak bisa dinonaktifkan, diganti nama, atau dihapus.';

    protected static function booted(): void
    {
        static::deleting(fn (Supplier $s) => $s->is_system ? throw new LogicException(self::SYSTEM_MESSAGE) : null);
    }

    // Kode berikutnya SUP-001, SUP-002, ... (tambah cepat dari dropdown; kode lain yang tidak berpola diabaikan).
    public static function nextCode(): string
    {
        $max = static::query()->where('code', 'like', 'SUP-%')->pluck('code')->map(fn ($c) => ctype_digit(substr($c, 4)) ? (int) substr($c, 4) : 0)->max() ?? 0;

        return sprintf('SUP-%03d', $max + 1);
    }

    // Buat supplier dengan kode otomatis; ulang bila kode tabrakan dengan pembuat lain (kolom unik).
    public static function createQuick(array $data): static
    {
        for ($try = 1; ; $try++) {
            try {
                return static::create($data + ['code' => static::nextCode()]);
            } catch (UniqueConstraintViolationException $e) {
                if ($try >= 5) {
                    throw $e;
                }
            }
        }
    }

    // Urutan semua dropdown supplier: supplier sistem (Kontan) dulu, lalu nama.
    public function scopeForSelect(Builder $query): Builder
    {
        return $query->orderByDesc('is_system')->orderBy('name');
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_system' => 'boolean', 'area_id' => 'integer'];
    }

    // Kunci wilayah "LEVEL:id" untuk GeoArea / kotak cari.
    public function areaKey(): ?string
    {
        return $this->area_level && $this->area_id ? $this->area_level.':'.$this->area_id : null;
    }
}
