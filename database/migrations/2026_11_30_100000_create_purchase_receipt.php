<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/* REMARK
 * Tujuan       : Penerimaan Pembelian (PV) modul Purchase: header (nomor PV-YYMM-0001, PO asal, pemasok, tanggal terima, surat jalan, penerima, status draft / confirmed / cancelled, dan nomor dokumen Penerimaan Barang di Stok yang dibuat otomatis saat Konfirmasi)
 *                dan baris (baris PO, qty diterima, satuan, harga dari PO, kedaluwarsa). Modul PUV diberikan RW ke grup ADMIN saja.
 * Output       : p_receipt, p_receipt_line, sys_user_group_module (PUV untuk ADMIN).
 * Dipakai oleh : PurchaseReceipt, PurchaseReceiptController.
 * Status       : Aktif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('p_receipt', function (Blueprint $table) {
            $table->id();
            $table->string('doc_no', 20)->unique();
            $table->date('doc_date');
            $table->foreignId('order_id')->constrained('p_order')->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('m_supplier')->restrictOnDelete();
            $table->string('ref_no', 60)->nullable();   // nomor surat jalan pemasok
            $table->string('receiver', 100)->nullable();
            $table->string('note', 255)->nullable();
            $table->string('status', 12)->default('draft');
            $table->foreignId('stock_document_id')->nullable()->constrained('rm_stock')->nullOnDelete();   // RCV yang dibuat saat Konfirmasi
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->string('reason', 255)->nullable();   // alasan dibatalkan
            $table->timestamps();
            $table->index(['status', 'doc_date']);
        });

        Schema::create('p_receipt_line', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_id')->constrained('p_receipt')->cascadeOnDelete();
            $table->foreignId('order_line_id')->constrained('p_order_line')->restrictOnDelete();
            $table->foreignId('item_id')->nullable()->constrained('m_item')->restrictOnDelete();
            $table->decimal('qty', 15, 3);
            $table->string('unit', 20);
            $table->decimal('price', 15, 2)->default(0);
            $table->date('expiry_date')->nullable();
            $table->timestamps();
        });

        $admin = DB::table('sys_user_group')->where('code', 'ADMIN')->value('id');
        if ($admin) {
            DB::table('sys_user_group_module')->insertOrIgnore(['group_id' => $admin, 'module' => 'PUV', 'access' => 'rw']);
        }
    }

    public function down(): void
    {
        DB::table('sys_user_group_module')->where('module', 'PUV')->delete();
        Schema::dropIfExists('p_receipt_line');
        Schema::dropIfExists('p_receipt');
    }
};
