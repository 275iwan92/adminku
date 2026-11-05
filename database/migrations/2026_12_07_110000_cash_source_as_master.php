<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/* REMARK
 * Tujuan       : Normalisasi Sumber Dana setara Kebutuhan: k_cash_in.source_id (nullable, indexed) menyimpan sumber yang dipilih (nama tetap disalin ke source_name sebagai cadangan), dan m_cash_source dapat
 *                dinonaktifkan (is_active) sehingga sumber yang sudah dipakai tidak dihapus keras. Uang Masuk lama dicocokkan lewat nama persis; sisanya source_id kosong (laporan tetap memakai teks).
 * Output       : m_cash_source.is_active, k_cash_in.source_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('m_cash_source', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('name');
        });
        Schema::table('k_cash_in', function (Blueprint $table) {
            $table->unsignedBigInteger('source_id')->nullable()->after('cash_account_id')->index();
        });

        foreach (DB::table('m_cash_source')->get() as $s) {
            DB::table('k_cash_in')->where('source_name', $s->name)->update(['source_id' => $s->id]);
        }
    }

    public function down(): void
    {
        Schema::table('k_cash_in', function (Blueprint $table) {
            $table->dropIndex(['source_id']);
            $table->dropColumn('source_id');
        });
        Schema::table('m_cash_source', fn (Blueprint $table) => $table->dropColumn('is_active'));
    }
};
