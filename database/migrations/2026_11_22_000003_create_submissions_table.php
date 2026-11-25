<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('original_filename');
            $table->string('file_type', 12);
            $table->string('status')->default('processing');
            $table->decimal('similarity_score', 5, 2)->default(0);
            $table->unsignedInteger('matched_words')->default(0);
            $table->unsignedInteger('total_words')->default(0);
            $table->longText('extracted_text')->nullable();
            $table->boolean('no_repository')->default(true);
            $table->json('report_data')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submissions');
    }
};
