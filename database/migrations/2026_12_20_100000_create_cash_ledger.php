<?php

use App\Services\CashLedger;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* REMARK
 * Tujuan       : Buku besar kas k_cash_ledger (hanya CashLedger yang menulis; baris tidak pernah diubah / dihapus, pembatalan = baris pembalik). Saldo kas = jumlah baris.
 *                Data lama dibawa masuk: saldo awal tiap kas = baris pertama, Uang Masuk / Uang Keluar yang sudah Disetujui = satu baris tiap dokumen (CashLedger::backfill, idempoten).
 * Output       : k_cash_ledger.
 * Dipakai oleh : CashLedger, CashAccount, CashCardController, CashReportController.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('k_cash_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_account_id')->constrained('m_cash_account')->restrictOnDelete();
            $table->date('moved_at');
            $table->string('type', 10);   // opening | in | out | adjust
            $table->string('doc_type', 12)->nullable();   // cash_in | cash_out | cash_adjust | opening
            $table->unsignedBigInteger('doc_id')->nullable();
            $table->string('doc_no', 20)->nullable();
            $table->string('note', 255)->nullable();
            $table->decimal('amount', 15, 2);   // bertanda: masuk +, keluar -
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('reversal_of')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['cash_account_id', 'moved_at', 'id']);
            $table->index(['doc_type', 'doc_id']);
        });

        app(CashLedger::class)->backfill();
    }

    public function down(): void
    {
        Schema::dropIfExists('k_cash_ledger');
    }
};
