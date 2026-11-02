<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Penerbitan SP 1 / SP 2 / SP 3 / PHK kepada karyawan. Surat terbit tidak diubah; hanya bisa dicabut. Status tampil dihitung (state()), bukan disimpan, kecuali Dicabut.
#[Fillable(['doc_no', 'year', 'seq', 'type', 'employee_id', 'issued_on', 'until_on', 'reason', 'rule_ref', 'signer_name', 'signer_title', 'status', 'revoke_reason', 'revoked_at', 'created_by'])]
class Discipline extends Model
{
    protected $table = 'h_discipline';

    public const TYPES = ['sp1' => 'SP 1', 'sp2' => 'SP 2', 'sp3' => 'SP 3', 'phk' => 'PHK'];

    public const LETTERS = ['sp1' => 'Surat Peringatan I', 'sp2' => 'Surat Peringatan II', 'sp3' => 'Surat Peringatan III', 'phk' => 'Surat Pemutusan Hubungan Kerja'];

    public const RANK = ['sp1' => 1, 'sp2' => 2, 'sp3' => 3, 'phk' => 4];

    public const VALID_MONTHS = 6;

    private const ROMAN = [1 => 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];

    protected function casts(): array
    {
        return ['issued_on' => 'date', 'until_on' => 'date', 'revoked_at' => 'datetime'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    // Nomor surat: urut/JENIS/bulan romawi/tahun, urut per tahun dan jenis (mis. 012/SP2/XI/2026). Dipanggil di dalam transaksi.
    public static function nextNumber(string $type, \DateTimeInterface $on): array
    {
        $year = (int) $on->format('Y');
        $seq = ((int) static::query()->where('year', $year)->where('type', $type)->lockForUpdate()->max('seq')) + 1;

        return [$year, $seq, sprintf('%03d/%s/%s/%d', $seq, strtoupper($type), self::ROMAN[(int) $on->format('n')], $year)];
    }

    // SP yang masih berlaku (tidak dicabut dan belum lewat berlaku s/d), pada tanggal tertentu.
    public function scopeInForce(Builder $q): Builder
    {
        return $q->where('status', 'issued')->whereIn('type', ['sp1', 'sp2', 'sp3'])->whereDate('until_on', '>=', today());
    }

    // Status tampil: revoked | expired | superseded | active (SP) atau issued (PHK). $all = penerbitan lain karyawan yang sama (untuk menentukan "Diganti").
    public function state($all): string
    {
        if ($this->status === 'revoked') {
            return 'revoked';
        }
        if ($this->type === 'phk') {
            return 'issued';
        }
        if ($this->until_on->lt(today())) {
            return 'expired';
        }
        $higher = $all->first(fn (Discipline $d) => $d->id !== $this->id && $d->status === 'issued' && $d->type !== 'phk' && self::RANK[$d->type] > self::RANK[$this->type] && $d->until_on->gte(today()) && $d->issued_on->gte($this->issued_on));

        return $higher ? 'superseded' : 'active';
    }

    public function stateLabel($all): string
    {
        $s = $this->state($all);

        return match ($s) {
            'revoked' => 'Dicabut', 'expired' => 'Kedaluwarsa', 'issued' => 'Terbit',
            'superseded' => 'Diganti',
            default => 'Aktif',
        };
    }
}
