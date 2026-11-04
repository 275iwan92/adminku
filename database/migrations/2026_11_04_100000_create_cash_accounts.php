<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* REMARK
 * Tujuan       : Master Kas / Rekening (modul KSR): kas tunai dan rekening bank, masing-masing punya saldo awal sendiri. Saldo berjalan dihitung dari Uang Masuk / Uang Keluar yang disetujui, tidak disimpan di sini.
 * Output       : m_cash_account.
 * Dipakai oleh : CashAccount, CashAccountController.
 * Status       : Aktif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('m_cash_account', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('type', 10)->default('cash');   // cash | bank
            $table->string('bank_name', 100)->nullable();
            $table->string('account_no', 30)->nullable();
            $table->string('account_holder', 100)->nullable();
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('m_cash_account');
    }
};
