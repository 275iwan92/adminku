<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Pemeriksaan lengkap perangkat (More details): satu baris per laporan agen Python. Kolom ringkas untuk kueri cepat, isi lengkap (software, patch, SPD, dst.) di payload JSON karena bentuknya beda tiap PC.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('ip', 45)->nullable();
            $table->string('platform', 30)->nullable();
            $table->string('hostname', 100)->nullable();
            $table->string('cpu', 150)->nullable();
            $table->decimal('ram_gb', 8, 1)->nullable();
            $table->longText('payload');
            $table->timestamp('checked_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_reports');
    }
};
