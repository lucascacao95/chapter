<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('original_name');
            $table->string('disk', 64);
            $table->string('storage_key', 1024);
            $table->string('format', 10);
            $table->string('mime_type');
            $table->bigInteger('size_bytes');
            $table->string('processing_status', 20)->default('pending');
            $table->string('processing_error_code', 64)->nullable();
            $table->json('metadata_overrides')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_files');
    }
};
