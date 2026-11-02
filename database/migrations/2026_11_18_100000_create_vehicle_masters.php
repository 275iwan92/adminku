<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* REMARK
 * Tujuan       : Master modul GA Perawatan Aset Kendaraan: jenis kendaraan beserta rencana perawatannya (pekerjaan, interval km dan/atau bulan) dan kendaraan itu sendiri (plat = kode).
 * Output       : m_vehicle_type, m_vehicle_type_plan, m_vehicle.
 * Dipakai oleh : Vehicle, VehicleType, VehicleTypePlan, VehicleController, VehicleTypeController.
 * Status       : Aktif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('m_vehicle_type', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Rencana perawatan per jenis: interval km dan/atau bulan, mana yang tercapai lebih dulu. Dipasang di master, tidak diketik ulang tiap servis.
        Schema::create('m_vehicle_type_plan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_type_id')->constrained('m_vehicle_type')->cascadeOnDelete();
            $table->string('name', 100);
            $table->unsignedInteger('interval_km')->nullable();
            $table->unsignedSmallInteger('interval_months')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->unique(['vehicle_type_id', 'name']);
        });

        Schema::create('m_vehicle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_type_id')->constrained('m_vehicle_type');
            $table->string('plate', 20)->unique();
            $table->string('name', 100);
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('vin', 40)->nullable();
            $table->string('engine_no', 40)->nullable();
            $table->boolean('kir_required')->default(true);
            $table->unsignedInteger('odometer_km')->default(0);   // odometer terakhir; hanya naik, diperbarui lewat catatan odometer / servis
            $table->date('odometer_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('note', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('m_vehicle');
        Schema::dropIfExists('m_vehicle_type_plan');
        Schema::dropIfExists('m_vehicle_type');
    }
};
