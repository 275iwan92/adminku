<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/* REMARK
 * Tujuan       : Pesanan Pembelian (PO) modul Purchase: header (nomor PO-YYMM-0001, pemasok, syarat bayar, tanggal kirim, kirim ke, PPN opsional, status) dan baris barang (barang master atau teks bebas, qty, satuan, harga satuan, jumlah diterima, PP asal).
 *                Status: draft, approval (diajukan), approved, rejected, cancelled; partial dan done diisi nanti oleh Penerimaan Pembelian (PV). Modul PUK (Keputusan Pesanan) dan PUO (PO) diberikan RW ke grup ADMIN saja.
 * Output       : p_order, p_order_line, sys_user_group_module (PUK, PUO untuk ADMIN).
 * Dipakai oleh : PurchaseOrder, PurchaseOrderController, PurchaseDecisionController.
 * Status       : Aktif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('p_order', function (Blueprint $table) {
            $table->id();
            $table->string('doc_no', 20)->unique();
            $table->date('doc_date');
            $table->foreignId('supplier_id')->constrained('m_supplier')->restrictOnDelete();
            $table->unsignedSmallInteger('payment_term_days')->nullable();
            $table->date('need_date')->nullable();
            $table->string('ship_to', 150)->nullable();
            $table->decimal('vat_pct', 5, 2)->default(0);
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

        Schema::create('p_order_line', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('p_order')->cascadeOnDelete();
            $table->foreignId('request_line_id')->nullable()->constrained('p_request_line')->nullOnDelete();
            $table->foreignId('item_id')->nullable()->constrained('m_item')->restrictOnDelete();
            $table->string('item_name', 150)->nullable();
            $table->decimal('qty', 15, 3);
            $table->string('unit', 20);
            $table->decimal('price', 15, 2)->default(0);
            $table->decimal('received_qty', 15, 3)->default(0);
            $table->string('note', 255)->nullable();
            $table->timestamps();
        });

        $admin = DB::table('sys_user_group')->where('code', 'ADMIN')->value('id');
        if ($admin) {
            foreach (['PUK', 'PUO'] as $module) {
                DB::table('sys_user_group_module')->insertOrIgnore(['group_id' => $admin, 'module' => $module, 'access' => 'rw']);
            }
        }
    }

    public function down(): void
    {
        DB::table('sys_user_group_module')->whereIn('module', ['PUK', 'PUO'])->delete();
        Schema::dropIfExists('p_order_line');
        Schema::dropIfExists('p_order');
    }
};
