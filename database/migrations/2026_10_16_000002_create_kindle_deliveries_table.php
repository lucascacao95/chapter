<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kindle_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_file_id')->index()->constrained()->cascadeOnDelete();
            $table->uuid('idempotency_key')->unique();
            $table->string('recipient');
            $table->string('status', 20)->default('queued');
            $table->string('error_code', 64)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kindle_deliveries');
    }
};
