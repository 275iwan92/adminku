<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\FacAsset;
use App\Models\FacHandover;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/* REMARK
 * Tujuan       : Penggunaan dan penyerahan aset: posisi dan pemegang aset, efek Serah Terima yang disetujui (pinjam / bawa pulang menetapkan pemegang dan tanggal wajib kembali; pengembalian membersihkannya dan memindahkan kondisi;
 *                laporan hilang), daftar monitor GA (di luar kantor, terlambat kembali, hilang, dalam perbaikan, pemegang yang sudah keluar, temuan opname), dan aset per pemegang. Tidak menyentuh stok gudang.
 * Sumber hulu  : m_fac_asset, g_fac_handover, g_fac_check_line, m_employee.
 * Output       : apply(), monitor(), outside(), byHolder(), findings().
 * Dipakai oleh : FacHandoverController, FacCheckController, FacCareController, FacReportController, HomeCards, Opening.
 * Status       : Aktif.
 */
final class FacCustody
{
    public const POSITIONS = ['office' => 'Di kantor', 'loan' => 'Dipinjam', 'home' => 'Dibawa pulang', 'repair' => 'Dalam perbaikan', 'lost' => 'Hilang'];

    public const POSITION_TONES = ['Di kantor' => 'success', 'Dipinjam' => 'info', 'Dibawa pulang' => 'warn', 'Dalam perbaikan' => 'warn', 'Hilang' => 'danger'];

    public const STATUS = ['draft' => 'Draf', 'approval' => 'Diajukan', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', 'cancelled' => 'Dibatalkan'];

    public const STATUS_TONES = ['Draf' => 'neutral', 'Diajukan' => 'warn', 'Disetujui' => 'success', 'Ditolak' => 'danger', 'Dibatalkan' => 'danger'];

    /** Efek dokumen yang disetujui pada aset. */
    public static function apply(FacHandover $h): void
    {
        $a = FacAsset::query()->findOrFail($h->asset_id);
        match ($h->type) {
            'loan', 'home' => $a->update(['position' => $h->type, 'holder_employee_id' => $h->employee_id, 'holder_department_id' => $h->department_id, 'due_back' => $h->expected_return]),
            'return' => $a->update([
                'position' => $h->condition_out === 'rusak' ? 'repair' : 'office', 'holder_employee_id' => null, 'holder_department_id' => null, 'due_back' => null,
                'cond' => in_array($h->condition_out, ['baik', 'perlu', 'rusak'], true) ? $h->condition_out : $a->cond,
            ]),
            'lost' => $a->update(['position' => 'lost', 'due_back' => null]),
        };
    }

    /** Angka monitor dalam satu kueri (untuk dashboard dan kartu Beranda). */
    public static function counts(): array
    {
        $r = FacAsset::query()->where('is_active', true)->selectRaw(
            "coalesce(sum(position in ('loan','home')), 0) out_n, coalesce(sum(position in ('loan','home') and due_back < ?), 0) late, coalesce(sum(position = 'lost'), 0) lost, coalesce(sum(position = 'repair'), 0) repair",
            [today()->toDateString()],
        )->first();

        return ['out' => (int) $r->out_n, 'overdue' => (int) $r->late, 'lost' => (int) $r->lost, 'repair' => (int) $r->repair];
    }

    /** Aset di luar kantor, yang terlambat dulu. @return Collection<int, FacAsset> */
    public static function outside(?int $limit = null): Collection
    {
        return FacAsset::query()->with('holderEmployee', 'holderDepartment')->where('is_active', true)->whereIn('position', ['loan', 'home'])
            ->orderByRaw('case when due_back is null then 1 else 0 end')->orderBy('due_back')->orderBy('asset_code')->when($limit, fn ($q) => $q->limit($limit))->get();
    }

    /** Karyawan sudah keluar (nonaktif) yang masih tercatat memegang aset. @return Collection<int, Employee> */
    public static function leftHolders(): Collection
    {
        return Employee::query()->where('is_active', false)->whereIn('id', FacAsset::query()->where('is_active', true)->whereNotNull('holder_employee_id')->whereIn('position', ['loan', 'home'])->select('holder_employee_id'))->get();
    }

    /** Aset yang pemeriksaan fisik terakhirnya tidak ditemukan. @return Collection<int, FacAsset> */
    public static function findings(): Collection
    {
        $latest = DB::table('g_fac_check_line')->selectRaw('max(id) id')->groupBy('asset_id');
        $ids = DB::table('g_fac_check_line')->whereIn('id', $latest)->where('found', false)->pluck('asset_id');

        return FacAsset::query()->with('holderEmployee', 'place')->where('is_active', true)->whereIn('id', $ids)->orderBy('asset_code')->get();
    }

    /** Aset yang seharusnya diperiksa untuk satu orang atau ruang. */
    public static function scope(?int $employeeId, ?int $placeId): Collection
    {
        return FacAsset::query()->with('category', 'place')->where('is_active', true)
            ->when($employeeId, fn ($q) => $q->where('holder_employee_id', $employeeId)->whereIn('position', ['loan', 'home']))
            ->when(! $employeeId && $placeId, fn ($q) => $q->where('place_id', $placeId)->where('position', 'office'))
            ->orderBy('asset_code')->get();
    }
}
