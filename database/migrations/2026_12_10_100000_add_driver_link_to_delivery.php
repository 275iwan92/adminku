<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/* REMARK
 * Tujuan       : Layar sopir di HP tanpa login: tiap trip punya tautan rahasia (token) dan tiap alamat mencatat jam tiba asli (arrived_at). Trip yang sudah Ditugaskan dibekali token.
 * Output       : delivery_trip.token (unik), delivery_stop.arrived_at.
 * Dipakai oleh : DeliveryTrip, DriverTripController, DeliveryTripController.
 * Status       : Aktif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_trip', fn (Blueprint $table) => $table->string('token', 40)->nullable()->unique()->after('doc_no'));
        Schema::table('delivery_stop', fn (Blueprint $table) => $table->timestamp('arrived_at')->nullable()->after('travel_min'));
        DB::table('delivery_trip')->where('status', 'assigned')->pluck('id')->each(fn ($id) => DB::table('delivery_trip')->where('id', $id)->update(['token' => Str::random(40)]));
    }

    public function down(): void
    {
        Schema::table('delivery_stop', fn (Blueprint $table) => $table->dropColumn('arrived_at'));
        Schema::table('delivery_trip', function (Blueprint $table) {
            $table->dropUnique(['token']);
            $table->dropColumn('token');
        });
    }
};
