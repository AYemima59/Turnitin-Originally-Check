<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tambah kolom kuota pada tabel users (gratis 1x download di awal)
        Schema::table('users', function (Blueprint $table) {
            $table->integer('download_quota')->default(1)->after('role');
        });

        // 2. Tambah penanda agar dokumen yang sudah dibayar tidak potong kuota 2x
        Schema::table('submissions', function (Blueprint $table) {
            $table->boolean('is_unlocked')->default(false);
        });

        // 3. Buat tabel riwayat pembayaran manual
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('package_name');
            $table->integer('quota_amount');
            $table->integer('price');
            $table->string('proof_image');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumn('is_unlocked');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('download_quota');
        });
    }
};