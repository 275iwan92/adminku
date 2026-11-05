<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/* REMARK
 * Tujuan       : Uang Keluar punya Kas Asal sendiri (k_cash_out.cash_account_id) dan tautan ke Uang Masuk menjadi opsional (cash_in_id boleh kosong). Data lama diisi dari kas Uang Masuk yang ditaut.
 *                Saldo kas = saldo awal + Uang Masuk disetujui - Uang Keluar disetujui pada kas itu (CashAccount::currentBalance memakai kolom baru ini).
 * Output       : k_cash_out.cash_account_id (FK m_cash_account), k_cash_out.cash_in_id nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('k_cash_out', function (Blueprint $table) {
            $table->foreignId('cash_account_id')->nullable()->after('cash_in_id')->constrained('m_cash_account')->restrictOnDelete();
        });

        DB::table('k_cash_out')->orderBy('id')->each(function ($o) {
            DB::table('k_cash_out')->where('id', $o->id)->update(['cash_account_id' => DB::table('k_cash_in')->where('id', $o->cash_in_id)->value('cash_account_id')]);
        });

        Schema::table('k_cash_out', function (Blueprint $table) {
            $table->unsignedBigInteger('cash_in_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Uang Keluar tanpa tautan tidak bisa ada lagi setelah kolom kembali wajib: baris itu dihapus (hanya terjadi pada rollback).
        DB::table('k_cash_out')->whereNull('cash_in_id')->delete();
        Schema::table('k_cash_out', function (Blueprint $table) {
            $table->unsignedBigInteger('cash_in_id')->nullable(false)->change();
        });
        Schema::table('k_cash_out', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cash_account_id');
        });
    }
};
