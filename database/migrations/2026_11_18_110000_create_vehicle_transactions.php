<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* REMARK
 * Tujuan       : Transaksi GA Perawatan Aset Kendaraan: perpanjangan dokumen (pajak, STNK, KIR, asuransi), servis beserta pekerjaannya, dan catatan odometer (awal, manual, dari servis).
 *                Masa berlaku dokumen sekarang = berlaku-sampai terbesar per jenis; jadwal servis berikutnya dihitung dari servis terakhir tiap butir rencana. Modul ini tidak menyentuh stok gudang.
 * Output       : g_vehicle_doc, g_vehicle_service, g_vehicle_service_line, g_vehicle_odometer.
 * Dipakai oleh : VehicleDoc, VehicleService, VehicleServiceLine, VehicleOdometer, App\Services\VehicleCare.
 * Status       : Aktif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('g_vehicle_doc', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained('m_vehicle');
            $table->string('doc_type', 12);   // pajak | stnk | kir | asuransi
            $table->string('doc_no', 60)->nullable();
            $table->date('paid_at');
            $table->date('valid_until');
            $table->decimal('cost', 15, 2)->default(0);
            $table->string('attachment_path', 255)->nullable();
            $table->string('attachment_name', 150)->nullable();
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->index(['vehicle_id', 'doc_type', 'valid_until']);
        });

        Schema::create('g_vehicle_service', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained('m_vehicle');
            $table->date('serviced_at');
            $table->unsignedInteger('odometer_km');
            $table->string('workshop', 100)->nullable();
            $table->decimal('cost', 15, 2)->default(0);
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->index(['vehicle_id', 'serviced_at']);
        });

        // Satu baris per pekerjaan yang dikerjakan; nama disalin supaya riwayat tetap terbaca bila butir rencananya diganti nama / dibuang.
        Schema::create('g_vehicle_service_line', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_service_id')->constrained('g_vehicle_service')->cascadeOnDelete();
            $table->foreignId('vehicle_type_plan_id')->nullable()->constrained('m_vehicle_type_plan')->nullOnDelete();
            $table->string('name', 100);
            $table->index('vehicle_type_plan_id');
        });

        Schema::create('g_vehicle_odometer', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained('m_vehicle');
            $table->date('read_at');
            $table->unsignedInteger('km');
            $table->string('source', 10);   // awal | manual | servis
            $table->string('recorded_by', 100)->nullable();
            $table->string('photo_path', 255)->nullable();
            $table->string('photo_name', 150)->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->index(['vehicle_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('g_vehicle_odometer');
        Schema::dropIfExists('g_vehicle_service_line');
        Schema::dropIfExists('g_vehicle_service');
        Schema::dropIfExists('g_vehicle_doc');
    }
};
