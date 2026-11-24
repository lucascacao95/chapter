<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BookRating extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['book_id', 'book_file_id', 'user_id', 'rating', 'review'];

    protected function casts(): array
    {
        return ['rating' => 'float', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => false);
        static::deleting(fn () => false);
    }

    public function feedActivity(): HasOne
    {
        return $this->hasOne(FeedActivity::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function bookFile(): BelongsTo
    {
        return $this->belongsTo(BookFile::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
