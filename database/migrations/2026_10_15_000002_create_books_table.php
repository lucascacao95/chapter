<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('collection_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->string('title');
            $table->json('authors')->nullable();
            $table->integer('page_count')->nullable();
            $table->string('publisher')->nullable();
            $table->string('edition')->nullable();
            $table->text('description')->nullable();
            $table->string('language', 35)->nullable();
            $table->smallInteger('published_year')->nullable();
            $table->string('isbn', 32)->nullable();
            $table->string('cover_path', 1024)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
