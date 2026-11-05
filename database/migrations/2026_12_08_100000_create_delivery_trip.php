<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/* REMARK
 * Tujuan       : Pengiriman (PGK): satu trip = satu kendaraan + satu crew (sopir + asisten), berisi urutan alamat (stop) antar / ambil. Tiap stop membawa barang yang diketik (items, JSON: nama, jumlah, satuan, kg)
 *                atau ditautkan ke satu atau beberapa Surat Jalan (dokumen Pengeluaran Barang terkonfirmasi; satu surat jalan hanya di satu stop). Status draft -> assigned (Tugaskan) | cancelled.
 *                Menu Surat Jalan (SJL) hanya daftar dan jalan pintas ke Pengeluaran Barang. PGK dan SJL diberikan RW ke grup ADMIN.
 * Output       : delivery_trip, delivery_stop, delivery_stop_doc, sys_user_group_module (PGK, SJL untuk ADMIN).
 * Dipakai oleh : DeliveryTrip, DeliveryStop, DeliveryTripController, DeliveryNoteController.
 * Status       : Aktif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_trip', function (Blueprint $table) {
            $table->id();
            $table->string('doc_no', 20)->nullable()->unique();   // nomor lahir saat Tugaskan
            $table->date('trip_date');
            $table->foreignId('vehicle_id')->constrained('m_vehicle')->restrictOnDelete();
            $table->string('driver', 100);
            $table->string('assistant', 100)->nullable();
            $table->string('depart_time', 5)->default('08:00');
            $table->unsignedSmallInteger('return_min')->default(25);   // tempuh stop terakhir ke kantor
            $table->string('note', 255)->nullable();
            $table->string('status', 12)->default('draft');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->string('reason', 255)->nullable();   // alasan dibatalkan
            $table->timestamps();
            $table->index(['status', 'trip_date']);
        });

        Schema::create('delivery_stop', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained('delivery_trip')->cascadeOnDelete();
            $table->unsignedSmallInteger('seq');
            $table->string('kind', 3);   // ant = antar, amb = ambil
            $table->string('customer', 100);
            $table->string('address', 255)->nullable();
            $table->unsignedSmallInteger('travel_min')->default(15);   // tempuh dari stop sebelumnya (atau kantor)
            $table->json('items')->nullable();   // [[nama, jumlah, satuan, kg], ...] bila tanpa surat jalan
            $table->timestamps();
        });

        Schema::create('delivery_stop_doc', function (Blueprint $table) {
            $table->foreignId('stop_id')->constrained('delivery_stop')->cascadeOnDelete();
            $table->foreignId('stock_document_id')->unique()->constrained('rm_stock')->restrictOnDelete();   // satu surat jalan hanya di satu stop
            $table->primary(['stop_id', 'stock_document_id']);
        });

        $admin = DB::table('sys_user_group')->where('code', 'ADMIN')->value('id');
        if ($admin) {
            foreach (['PGK', 'SJL'] as $module) {
                DB::table('sys_user_group_module')->insertOrIgnore(['group_id' => $admin, 'module' => $module, 'access' => 'rw']);
            }
        }
    }

    public function down(): void
    {
        DB::table('sys_user_group_module')->whereIn('module', ['PGK', 'SJL'])->delete();
        Schema::dropIfExists('delivery_stop_doc');
        Schema::dropIfExists('delivery_stop');
        Schema::dropIfExists('delivery_trip');
    }
};
