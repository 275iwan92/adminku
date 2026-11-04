<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/* REMARK
 * Tujuan       : Modul Kas: Uang Masuk (k_cash_in, modul KSM) dan Uang Keluar (k_cash_out, modul KSK), wajib ditaut ke satu Uang Masuk sebagai sumber dananya (pelacakan dana per sumber).
 *                Status draft, approval (diajukan), approved, rejected, cancelled, pola sama Purchase. Jumlah Uang Keluar tidak boleh melebihi sisa saldo Uang Masuk yang ditaut (dicek di controller). Modul KSM/KSK/KSR diberikan RW ke grup ADMIN saja.
 * Output       : k_cash_in, k_cash_out, sys_user_group_module (KSM, KSK, KSR untuk ADMIN).
 * Dipakai oleh : CashIn, CashOut, CashInController, CashOutController.
 * Status       : Aktif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('k_cash_in', function (Blueprint $table) {
            $table->id();
            $table->string('doc_no', 20)->nullable()->unique();   // draf belum bernomor: nomor lahir saat Confirm
            $table->date('doc_date');
            $table->foreignId('cash_account_id')->constrained('m_cash_account')->restrictOnDelete();
            $table->string('source_name', 150);
            $table->decimal('amount', 15, 2);
            $table->string('description', 255)->nullable();
            $table->string('attachment', 255)->nullable();
            $table->string('status', 12)->default('draft');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('reason', 255)->nullable();   // alasan ditolak / dibatalkan
            $table->timestamps();
            $table->index(['status', 'doc_date']);
        });

        Schema::create('k_cash_out', function (Blueprint $table) {
            $table->id();
            $table->string('doc_no', 20)->nullable()->unique();   // draf belum bernomor: nomor lahir saat Confirm
            $table->date('doc_date');
            $table->foreignId('cash_in_id')->constrained('k_cash_in')->restrictOnDelete();   // sumber dana wajib; kas asal ikut cashIn->cashAccount
            $table->decimal('amount', 15, 2);
            $table->string('purpose', 150);
            $table->string('description', 255)->nullable();
            $table->string('attachment', 255)->nullable();
            $table->string('status', 12)->default('draft');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('reason', 255)->nullable();
            $table->timestamps();
            $table->index(['status', 'doc_date']);
        });

        $admin = DB::table('sys_user_group')->where('code', 'ADMIN')->value('id');
        if ($admin) {
            DB::table('sys_user_group_module')->insertOrIgnore([
                ['group_id' => $admin, 'module' => 'KSM', 'access' => 'rw'],
                ['group_id' => $admin, 'module' => 'KSK', 'access' => 'rw'],
                ['group_id' => $admin, 'module' => 'KSR', 'access' => 'rw'],
            ]);
        }
    }

    public function down(): void
    {
        DB::table('sys_user_group_module')->whereIn('module', ['KSM', 'KSK', 'KSR'])->delete();
        Schema::dropIfExists('k_cash_out');
        Schema::dropIfExists('k_cash_in');
    }
};
