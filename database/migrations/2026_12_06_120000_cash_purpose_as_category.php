<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/* REMARK
 * Tujuan       : Kebutuhan Uang Keluar menjadi KATEGORI pendek yang bisa dibuat grafik: m_cash_purpose dapat dinonaktifkan (is_active; kategori yang sudah dipakai tidak dihapus keras) dan k_cash_out menyimpan purpose_id
 *                (nama tetap disalin ke kolom purpose sebagai cadangan). Contoh awal diganti kategori pendek; contoh lama yang belum dipakai dihapus. Uang Keluar lama dicocokkan lewat nama persis, sisanya purpose_id kosong
 *                (laporan menampilkannya sebagai "Belum dikategorikan").
 * Output       : m_cash_purpose.is_active, k_cash_out.purpose_id.
 */
return new class extends Migration
{
    private const OLD = ['Operasional kantor', 'Pembelian ATK', 'Konsumsi acara', 'Transportasi dan perjalanan dinas', 'Sewa tempat kegiatan', 'Perawatan dan perbaikan', 'Honor dan upah harian', 'Bantuan sosial'];

    private const NEW = ['Transportasi', 'Pembelian bahan pokok', 'Pembelian bahan pendukung', 'Operasional kantor', 'Konsumsi', 'Perawatan dan perbaikan', 'Honor dan upah', 'Bantuan sosial', 'Lainnya'];

    public function up(): void
    {
        Schema::table('m_cash_purpose', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('name');
        });
        Schema::table('k_cash_out', function (Blueprint $table) {
            $table->unsignedBigInteger('purpose_id')->nullable()->after('purpose')->index();
        });

        // Contoh lama yang belum dipakai dokumen mana pun diganti kategori pendek.
        $used = DB::table('k_cash_out')->pluck('purpose')->all();
        DB::table('m_cash_purpose')->whereIn('name', array_diff(self::OLD, self::NEW, $used))->delete();
        $now = now();
        foreach (self::NEW as $name) {
            if (! DB::table('m_cash_purpose')->where('name', $name)->exists()) {
                DB::table('m_cash_purpose')->insert(['name' => $name, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
        foreach (DB::table('m_cash_purpose')->get() as $p) {
            DB::table('k_cash_out')->where('purpose', $p->name)->update(['purpose_id' => $p->id]);
        }
    }

    public function down(): void
    {
        Schema::table('k_cash_out', function (Blueprint $table) {
            $table->dropIndex(['purpose_id']);
            $table->dropColumn('purpose_id');
        });
        Schema::table('m_cash_purpose', fn (Blueprint $table) => $table->dropColumn('is_active'));
    }
};
