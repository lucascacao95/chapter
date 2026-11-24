<?php

namespace App\Models;

use Database\Factories\BookFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

#[Fillable(['title', 'authors', 'page_count', 'publisher', 'edition', 'description', 'language', 'published_year', 'isbn', 'collection_id'])]
#[Hidden(['cover_path'])]
class Book extends Model
{
    /** @use HasFactory<BookFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'authors' => 'array',
            'page_count' => 'integer',
            'published_year' => 'integer',
            'collection_id' => 'integer',
        ];
    }

    // Keep deletion observers, reference collection and queued cleanup in one transaction.
    public function delete()
    {
        return DB::transaction(fn () => parent::delete());
    }

    public function feedActivities(): HasMany
    {
        return $this->hasMany(FeedActivity::class);
    }

    public function feedActivity(): HasOne
    {
        return $this->hasOne(FeedActivity::class)->where('type', 'book_added');
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(BookRating::class);
    }

    public function latestRating(): HasOne
    {
        return $this->hasOne(BookRating::class)->latestOfMany();
    }

    public function file(): HasOne
    {
        return $this->hasOne(BookFile::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Collection, $this> */
    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }
}
