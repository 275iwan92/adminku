<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/* REMARK
 * Tujuan       : Konsep Confirm tmmockup untuk Purchase: nomor dokumen (PP, PO, PV) BARU lahir saat Confirm, bukan saat draf disimpan. Draf tidak bernomor, jadi doc_no boleh kosong (indeks unik tetap: kosong ganda diizinkan).
 *                Draf yang sudah bernomor sebelum perubahan ini tetap memakai nomornya.
 * Output       : p_request.doc_no, p_order.doc_no, p_receipt.doc_no menjadi nullable.
 * Dipakai oleh : PurchaseRequest, PurchaseOrder, PurchaseReceipt.
 * Status       : Aktif.
 */
return new class extends Migration
{
    private const TABLES = ['p_request', 'p_order', 'p_receipt'];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->string('doc_no', 20)->nullable()->change());
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            // Draf yang belum bernomor diberi nomor sementara supaya kolom bisa kembali wajib diisi.
            DB::table($name)->whereNull('doc_no')->orderBy('id')->each(fn ($row) => DB::table($name)->where('id', $row->id)->update(['doc_no' => 'DRAF-'.$row->id]));
            Schema::table($name, fn (Blueprint $table) => $table->string('doc_no', 20)->nullable(false)->change());
        }
    }
};
