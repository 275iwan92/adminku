<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* REMARK
 * Tujuan       : Layar sopir HP: hasil pindai kamera per alamat (kode, cocok / tidak, waktu) dan riwayat posisi GPS sopir selama trip berjalan (dibersihkan 7 hari, delivery:prune-positions).
 * Output       : delivery_stop.scanned_at / scan_code / scan_ok, tabel delivery_position.
 * Dipakai oleh : DriverTripController, DeliveryTripController, DeliveryPosition.
 * Status       : Aktif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_stop', function (Blueprint $table) {
            $table->timestamp('scanned_at')->nullable()->after('arrived_at');
            $table->string('scan_code', 80)->nullable()->after('scanned_at');
            $table->boolean('scan_ok')->nullable()->after('scan_code');
        });
        Schema::create('delivery_position', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained('delivery_trip')->cascadeOnDelete();
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->unsignedSmallInteger('accuracy')->nullable();
            $table->timestamp('recorded_at');
            $table->index(['trip_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_position');
        Schema::table('delivery_stop', fn (Blueprint $table) => $table->dropColumn(['scanned_at', 'scan_code', 'scan_ok']));
    }
};
