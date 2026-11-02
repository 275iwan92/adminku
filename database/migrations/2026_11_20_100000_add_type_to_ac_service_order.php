<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* REMARK
 * Tujuan       : Tipe pesanan kunjungan AC: Preventif (jadwal rutin cuci / servis besar) atau Breakdown (AC rusak). Breakdown membawa keluhan dan tingkat (Mendesak / Biasa); Mendesak boleh di luar kapasitas harian
 *                dan hari kunjungan dengan alasan wajib (override_reason). Pesanan lama otomatis Preventif.
 * Output       : kolom type, complaint, priority, override_reason pada g_ac_service_order.
 * Dipakai oleh : AcOrder, App\Services\AcCare, AcVisits.
 * Status       : Aktif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('g_ac_service_order', function (Blueprint $table) {
            $table->string('type', 10)->default('preventive')->after('status');   // preventive | breakdown
            $table->string('complaint', 255)->nullable()->after('type');
            $table->string('priority', 8)->nullable()->after('complaint');   // urgent | normal (hanya Breakdown)
            $table->string('override_reason', 255)->nullable()->after('priority');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::table('g_ac_service_order', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn(['type', 'complaint', 'priority', 'override_reason']);
        });
    }
};
