<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            // NO ACTION permits whole-book cascades, but rejects deleting a referenced file alone.
            $table->foreignId('book_file_id')->nullable()->constrained()->noActionOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('rating', 2, 1);
            $table->text('review')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['book_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_ratings');
    }
};
