<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/* REMARK
 * Tujuan       : Penyesuaian Kas (modul KSA): koreksi saldo satu kas (tambah / kurangi) dengan alasan wajib. Status draft -> approval -> approved | rejected, boleh cancelled. Baris buku besar baru ditulis saat Approve (selalu oleh Supervisor).
 * Output       : k_cash_adjust, sys_user_group_module (KSA, KSB untuk ADMIN).
 * Dipakai oleh : CashAdjustment, CashAdjustmentController, CashLedger.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('k_cash_adjust', function (Blueprint $table) {
            $table->id();
            $table->string('doc_no', 20)->nullable()->unique();   // draf belum bernomor: nomor lahir saat Confirm
            $table->date('doc_date');
            $table->foreignId('cash_account_id')->constrained('m_cash_account')->restrictOnDelete();
            $table->string('direction', 3);   // add | sub
            $table->decimal('amount', 15, 2);
            $table->string('cause', 255);   // alasan penyesuaian (wajib)
            $table->string('status', 12)->default('draft');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('reason', 255)->nullable();   // alasan ditolak / dibatalkan
            $table->timestamps();
            $table->index(['status', 'doc_date']);
        });

        // Modul baru (KSA Penyesuaian Kas, KSB Kartu Saldo Kas) diberikan RW ke grup ADMIN, seperti modul Kas lain.
        $admin = DB::table('sys_user_group')->where('code', 'ADMIN')->value('id');
        if ($admin) {
            foreach (['KSA', 'KSB'] as $module) {
                DB::table('sys_user_group_module')->insertOrIgnore(['group_id' => $admin, 'module' => $module, 'access' => 'rw']);
            }
        }
    }

    public function down(): void
    {
        DB::table('sys_user_group_module')->whereIn('module', ['KSA', 'KSB'])->delete();
        Schema::dropIfExists('k_cash_adjust');
    }
};
