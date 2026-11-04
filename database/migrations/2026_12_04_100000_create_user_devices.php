<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Perangkat Saya: satu baris per sesi login aktif (jalan di driver sesi apa pun, termasuk redis yang tidak bisa didaftar).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('session_id', 100)->unique();
            $table->string('ip', 45)->nullable();
            $table->string('agent', 255)->nullable();
            $table->timestamp('last_active_at')->index();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_devices');
    }
};
