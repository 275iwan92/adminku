<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* REMARK
 * Tujuan       : Master modul GA Perawatan AC: area / gedung (denah: gambar unggahan atau denah skematis dari persegi ruang), ruang (kode ruang, persegi di denah, PIC), unit AC (kode aset permanen unik, posisi titik di denah)
 *                dan vendor jasa AC (nomor WhatsApp + e-mail untuk pemberitahuan). Koordinat denah dalam persen (0-100) dari lebar / tinggi denah, sehingga sama untuk denah skematis maupun gambar unggahan.
 *                Vendor AC dibuat sendiri (bukan m_supplier): m_supplier tidak punya e-mail dan akan mengotori pilihan supplier di Penerimaan Barang.
 * Output       : m_ac_area, m_ac_room, m_ac_vendor, m_ac_unit.
 * Dipakai oleh : AcArea, AcRoom, AcVendor, AcUnit, App\Services\AcCare.
 * Status       : Aktif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('m_ac_area', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->string('plan_path', 255)->nullable();   // denah unggahan di disk "ga"; kosong = denah skematis dari persegi ruang
            $table->string('plan_name', 150)->nullable();
            $table->unsignedSmallInteger('plan_w')->default(100);   // ukuran gambar (hanya perbandingan lebar : tinggi yang dipakai)
            $table->unsignedSmallInteger('plan_h')->default(60);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('m_ac_room', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->constrained('m_ac_area');
            $table->string('code', 20)->unique();   // kode ruang: bisa berubah (AC dipindah ke ruang lain), tampil di stiker bersama kode aset
            $table->string('name', 100);
            $table->decimal('x', 5, 2)->default(5);   // persegi ruang di denah (persen)
            $table->decimal('y', 5, 2)->default(5);
            $table->decimal('w', 5, 2)->default(25);
            $table->decimal('h', 5, 2)->default(25);
            $table->string('pic_name', 100)->nullable();
            $table->string('pic_phone', 30)->nullable();   // nomor WhatsApp PIC
            $table->string('pic_email', 150)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('m_ac_vendor', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->string('contact_name', 100)->nullable();
            $table->string('phone', 30)->nullable();   // nomor WhatsApp
            $table->string('email', 150)->nullable();
            $table->string('note', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('m_ac_unit', function (Blueprint $table) {
            $table->id();
            $table->string('asset_code', 20)->unique();   // kode aset permanen (stiker pada unit), tidak berubah saat AC dipindah
            $table->foreignId('room_id')->constrained('m_ac_room');
            $table->string('brand', 60);
            $table->string('model', 80)->nullable();
            $table->decimal('capacity_pk', 3, 1)->default(1);
            $table->date('installed_at')->nullable();
            $table->decimal('pos_x', 5, 2)->nullable();   // titik di denah (persen)
            $table->decimal('pos_y', 5, 2)->nullable();
            $table->date('last_wash_at')->nullable();   // cuci terakhir SEBELUM tercatat di sistem; sesudahnya riwayat g_ac_service_line yang dipakai
            $table->date('last_major_at')->nullable();
            $table->string('note', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('m_ac_unit');
        Schema::dropIfExists('m_ac_vendor');
        Schema::dropIfExists('m_ac_room');
        Schema::dropIfExists('m_ac_area');
    }
};
