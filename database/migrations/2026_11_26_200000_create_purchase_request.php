<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/* REMARK
 * Tujuan       : Permintaan Pembelian (PP) modul Purchase: header (nomor PP-YYMM-0001, bagian peminta, tanggal dibutuhkan, status) dan baris barang (barang master atau teks bebas, qty, satuan, estimasi harga satuan, jumlah sudah dipesan).
 *                Status: draft, approval (diajukan), approved, rejected, cancelled; partial dan done diisi nanti oleh PO. Modul PUP diberikan RW ke grup ADMIN saja.
 * Output       : p_request, p_request_line, sys_user_group_module (PUP untuk ADMIN).
 * Dipakai oleh : PurchaseRequest, PurchaseRequestController.
 * Status       : Aktif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('p_request', function (Blueprint $table) {
            $table->id();
            $table->string('doc_no', 20)->unique();
            $table->date('doc_date');
            $table->foreignId('department_id')->nullable()->constrained('m_department')->restrictOnDelete();
            $table->date('need_date')->nullable();
            $table->string('note', 255)->nullable();
            $table->string('status', 12)->default('draft');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('reason', 255)->nullable();   // alasan ditolak / dibatalkan
            $table->timestamps();
            $table->index(['status', 'doc_date']);
        });

        Schema::create('p_request_line', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('p_request')->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained('m_item')->restrictOnDelete();
            $table->string('item_name', 150)->nullable();   // barang yang belum ada di master
            $table->decimal('qty', 15, 3);
            $table->string('unit', 20);
            $table->decimal('est_price', 15, 2)->nullable();
            $table->string('note', 255)->nullable();
            $table->decimal('ordered_qty', 15, 3)->default(0);
            $table->timestamps();
        });

        $admin = DB::table('sys_user_group')->where('code', 'ADMIN')->value('id');
        if ($admin) {
            DB::table('sys_user_group_module')->insertOrIgnore(['group_id' => $admin, 'module' => 'PUP', 'access' => 'rw']);
        }
    }

    public function down(): void
    {
        DB::table('sys_user_group_module')->where('module', 'PUP')->delete();
        Schema::dropIfExists('p_request_line');
        Schema::dropIfExists('p_request');
    }
};
