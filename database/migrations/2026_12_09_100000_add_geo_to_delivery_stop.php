<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* REMARK
 * Tujuan       : Titik peta tiap alamat (lat, lng) untuk ilustrasi rute di planner Pengiriman. Boleh kosong: alamat tanpa titik tidak digambar.
 * Output       : delivery_stop.lat, delivery_stop.lng.
 * Dipakai oleh : DeliveryStop, DeliveryTripController, delivery-trips/planner.
 * Status       : Aktif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_stop', function (Blueprint $table) {
            $table->decimal('lat', 9, 6)->nullable()->after('address');
            $table->decimal('lng', 9, 6)->nullable()->after('lat');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_stop', fn (Blueprint $table) => $table->dropColumn(['lat', 'lng']));
    }
};
