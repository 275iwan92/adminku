<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/* REMARK
 * Tujuan       : GA Perawatan Gedung dan Aset: Gedung/Ruang (satu tabel, dua tingkat), Kategori Aset, Aset, Jadwal perawatan berkala, dan Perintah Kerja (tiket perawatan, siklus Draf > Diajukan > Disetujui oleh Supervisor).
 *                Vendor memakai m_ac_vendor yang sudah ada (identik: kontak, WhatsApp, e-mail). Modul FAM (panel) dan turunannya diberi RW ke grup bawaan ADMIN.
 * Output       : m_fac_place, m_fac_category, m_fac_asset, g_fac_plan, g_fac_order, sys_user_group_module.
 * Dipakai oleh : FacPlace, FacCategory, FacAsset, FacPlan, FacOrder, App\Services\FacCare.
 * Status       : Aktif.
 */
return new class extends Migration
{
    private const MODULES = ['FAM', 'FAT', 'FAJ', 'FAA', 'FAG', 'FAC', 'FAR'];

    public function up(): void
    {
        Schema::create('m_fac_place', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('m_fac_place');   // kosong = gedung / lokasi induk; terisi = ruang / area di dalamnya
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->string('pic_name', 100)->nullable();
            $table->string('note', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('m_fac_category', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->string('icon', 40)->default('fa-box');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('m_fac_asset', function (Blueprint $table) {
            $table->id();
            $table->string('asset_code', 20)->unique();   // AST-0001, permanen
            $table->string('name', 150);
            $table->foreignId('category_id')->constrained('m_fac_category');
            $table->foreignId('place_id')->nullable()->constrained('m_fac_place');
            $table->date('bought_at')->nullable();
            $table->decimal('price', 15, 2)->default(0);
            $table->date('warranty_until')->nullable();
            $table->string('cond', 10)->default('baik');   // baik | perlu | rusak | dihapus
            $table->string('pic_name', 100)->nullable();
            $table->string('photo_path', 255)->nullable();
            $table->string('photo_name', 150)->nullable();
            $table->string('note', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('g_fac_plan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->nullable()->constrained('m_fac_asset');   // jadwal per aset ATAU per gedung / ruang
            $table->foreignId('place_id')->nullable()->constrained('m_fac_place');
            $table->string('title', 150);
            $table->string('period', 10);   // weekly | monthly | quarterly | yearly
            $table->date('start_at');
            $table->date('last_done_at')->nullable();
            $table->foreignId('vendor_id')->nullable()->constrained('m_ac_vendor');
            $table->string('note', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('g_fac_order', function (Blueprint $table) {
            $table->id();
            $table->string('no', 20)->nullable()->unique();   // WO-YYMM-0001, lahir saat Confirm
            $table->foreignId('asset_id')->nullable()->constrained('m_fac_asset');
            $table->foreignId('place_id')->nullable()->constrained('m_fac_place');
            $table->foreignId('plan_id')->nullable()->constrained('g_fac_plan');
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->string('priority', 8)->default('normal');   // low | normal | high | urgent
            $table->string('status', 10)->default('draft');   // draft | approval | approved | rejected | cancelled
            $table->date('reported_at');
            $table->string('handler', 8)->default('vendor');   // vendor | internal
            $table->foreignId('vendor_id')->nullable()->constrained('m_ac_vendor');
            $table->string('technician', 100)->nullable();
            $table->decimal('labor_cost', 15, 2)->default(0);
            $table->decimal('material_cost', 15, 2)->default(0);
            $table->date('finished_at')->nullable();
            $table->string('note', 255)->nullable();
            $table->string('photo_before_path', 255)->nullable();
            $table->string('photo_before_name', 150)->nullable();
            $table->string('photo_after_path', 255)->nullable();
            $table->string('photo_after_name', 150)->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->string('reason', 255)->nullable();
            $table->timestamps();
            $table->index(['status', 'reported_at']);
        });

        $admin = DB::table('sys_user_group')->where('code', 'ADMIN')->value('id');
        foreach ($admin ? self::MODULES : [] as $module) {
            DB::table('sys_user_group_module')->insertOrIgnore(['group_id' => $admin, 'module' => $module, 'access' => 'rw']);
        }
    }

    public function down(): void
    {
        DB::table('sys_user_group_module')->whereIn('module', self::MODULES)->delete();
        foreach (['g_fac_order', 'g_fac_plan', 'm_fac_asset', 'm_fac_category', 'm_fac_place'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
