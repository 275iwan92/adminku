<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/* REMARK
 * Tujuan       : Master Pemasok untuk Purchase: email (cetakan PO), syarat bayar dalam hari (0 = tunai; nilai awal PO), catatan bebas. Semua boleh kosong, pemasok lama tidak berubah.
 * Output       : kolom email, payment_term_days, note di m_supplier; Kontan (pemasok sistem) diberi syarat bayar 0.
 * Dipakai oleh : Supplier, SupplierRequest, suppliers.form, Purchase (PO).
 * Status       : Aktif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('m_supplier', function (Blueprint $table) {
            $table->string('email', 150)->nullable()->after('contact_mobile');
            $table->unsignedSmallInteger('payment_term_days')->nullable()->after('email');
            $table->string('note', 255)->nullable()->after('payment_term_days');
        });
        DB::table('m_supplier')->where('is_system', true)->update(['payment_term_days' => 0]);
    }

    public function down(): void
    {
        Schema::table('m_supplier', function (Blueprint $table) {
            $table->dropColumn(['email', 'payment_term_days', 'note']);
        });
    }
};
