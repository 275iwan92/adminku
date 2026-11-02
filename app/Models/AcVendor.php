<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

// Vendor jasa perawatan AC. phone = nomor WhatsApp, email = alamat e-mail pemberitahuan kunjungan.
#[Fillable(['code', 'name', 'contact_name', 'phone', 'email', 'note', 'is_active'])]
class AcVendor extends Model
{
    protected $table = 'm_ac_vendor';

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    // Kode berikutnya VND-001, VND-002, ...
    public static function nextCode(): string
    {
        $max = static::query()->where('code', 'like', 'VND-%')->pluck('code')->map(fn ($c) => ctype_digit(substr($c, 4)) ? (int) substr($c, 4) : 0)->max() ?? 0;

        return sprintf('VND-%03d', $max + 1);
    }
}
