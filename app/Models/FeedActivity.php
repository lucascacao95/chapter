<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class FeedActivity extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['cover_disk', 'cover_path', 'source_key'];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'occurred_at' => 'datetime', 'published_at' => 'datetime', 'withdrawn_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function bookRating(): BelongsTo
    {
        return $this->belongsTo(BookRating::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where('status', 'published')->whereNotNull('published_at')
            ->where(fn (Builder $authors) => $authors->where('user_id', $user->id)
                ->orWhereIn('user_id', DB::table('user_follows')->select('followed_id')->where('follower_id', $user->id)));
    }
}
