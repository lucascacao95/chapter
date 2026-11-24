<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A table-level CHECK is supported by both PostgreSQL and SQLite.
        DB::statement('CREATE TABLE user_follows (
            follower_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            followed_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (follower_id, followed_id),
            CHECK (follower_id <> followed_id)
        )');
        Schema::table('user_follows', fn (Blueprint $table) => $table->index('followed_id'));

        Schema::create('feed_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->foreignId('book_rating_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('status', 16)->default('pending');
            $table->string('source_key', 100)->unique();
            $table->json('snapshot')->nullable();
            $table->string('cover_disk', 64)->nullable();
            $table->string('cover_path', 1024)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'published_at', 'id']);
            $table->index(['user_id', 'status', 'published_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_activities');
        Schema::dropIfExists('user_follows');
    }
};
