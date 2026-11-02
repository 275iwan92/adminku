<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* REMARK
 * Tujuan       : Transaksi GA Perawatan AC: pesanan kunjungan vendor (satu tanggal, satu vendor, beberapa AC; status Draf > Terkirim > Dikonfirmasi vendor > Selesai, atau Dijadwal ulang / Dibatalkan),
 *                barisnya (satu AC, jenis pekerjaan cuci rutin atau servis besar, tanggal selesai, foto sebelum / sesudah) dan riwayat jadwal ulang. Jatuh tempo berikutnya per AC dihitung dari baris SELESAI terakhir per jenis.
 *                Modul ini tidak menyentuh stok gudang.
 * Output       : g_ac_service_order, g_ac_service_line, g_ac_service_reschedule.
 * Dipakai oleh : AcOrder, AcOrderLine, AcReschedule, App\Services\AcCare, App\Services\AcVisits.
 * Status       : Aktif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('g_ac_service_order', function (Blueprint $table) {
            $table->id();
            $table->string('no', 20)->unique();   // ACV-YYMM-NNNN
            $table->foreignId('vendor_id')->constrained('m_ac_vendor');
            $table->date('visit_date');
            $table->time('visit_time');
            $table->string('status', 12)->default('draft');   // draft | sent | confirmed | done | rescheduled | cancelled
            $table->string('note', 255)->nullable();
            $table->string('reschedule_reason', 255)->nullable();   // alasan jadwal ulang terakhir (riwayat lengkap di g_ac_service_reschedule)
            $table->string('cancel_reason', 255)->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users');
            $table->dateTime('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users');
            $table->dateTime('done_at')->nullable();
            $table->foreignId('done_by')->nullable()->constrained('users');
            $table->dateTime('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->index(['vendor_id', 'visit_date']);
            $table->index('status');
        });

        Schema::create('g_ac_service_line', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('g_ac_service_order')->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained('m_ac_unit');
            $table->string('job', 5);   // wash (cuci rutin) | major (servis besar, sudah mencakup cuci)
            $table->date('done_at')->nullable();   // terisi saat kunjungan ditandai selesai: dasar jatuh tempo berikutnya
            $table->string('photo_before_path', 255)->nullable();
            $table->string('photo_before_name', 150)->nullable();
            $table->string('photo_after_path', 255)->nullable();
            $table->string('photo_after_name', 150)->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamps();
            $table->unique(['order_id', 'unit_id']);
            $table->index(['unit_id', 'done_at']);
        });

        Schema::create('g_ac_service_reschedule', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('g_ac_service_order')->cascadeOnDelete();
            $table->date('from_date');
            $table->time('from_time');
            $table->date('to_date');
            $table->time('to_time');
            $table->string('reason', 255);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('g_ac_service_reschedule');
        Schema::dropIfExists('g_ac_service_line');
        Schema::dropIfExists('g_ac_service_order');
    }
};
