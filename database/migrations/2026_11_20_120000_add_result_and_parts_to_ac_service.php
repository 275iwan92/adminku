<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* REMARK
 * Tujuan       : Catat hasil per AC pada kunjungan vendor: kondisi / temuan, penyebab dan tindakan (Breakdown), biaya jasa, dan spare-part yang diganti (nama bebas, jumlah, biaya opsional). Spare-part TIDAK terhubung ke stok gudang.
 * Output       : g_ac_service_line.findings / cause / action / labor_cost; tabel g_ac_service_part.
 * Dipakai oleh : AcOrderLine, AcOrderPart, App\Services\AcVisits.
 * Status       : Aktif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('g_ac_service_line', function (Blueprint $table) {
            $table->string('findings', 255)->nullable()->after('note');
            $table->string('cause', 255)->nullable()->after('findings');
            $table->string('action', 255)->nullable()->after('cause');
            $table->decimal('labor_cost', 14, 2)->nullable()->after('action');
        });
        Schema::create('g_ac_service_part', function (Blueprint $table) {
            $table->id();
            $table->foreignId('line_id')->constrained('g_ac_service_line')->cascadeOnDelete();
            $table->string('name', 100);
            $table->decimal('qty', 8, 2)->default(1);
            $table->decimal('cost', 14, 2)->nullable();
            $table->timestamps();
            $table->index('line_id');
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('g_ac_service_part');
        Schema::table('g_ac_service_line', function (Blueprint $table) {
            $table->dropColumn(['findings', 'cause', 'action', 'labor_cost']);
        });
    }
};
