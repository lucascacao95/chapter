<?php

namespace App\Services\Feed;

use App\Jobs\PublishFeedActivity;
use App\Models\Book;
use App\Models\BookRating;
use App\Models\FeedActivity;
use Illuminate\Support\Facades\DB;

class RequestFeedPublication
{
    public function handle(Book $book, ?BookRating $rating = null): FeedActivity
    {
        return DB::transaction(function () use ($book, $rating) {
            $book = Book::whereKey($book->id)->lockForUpdate()->firstOrFail();
            $type = $rating ? 'book_rated' : 'book_added';
            $activity = FeedActivity::firstOrCreate(['source_key' => $type.':'.($rating?->id ?? $book->id)], [
                'user_id' => $book->user_id,
                'book_id' => $book->id,
                'book_rating_id' => $rating?->id,
                'type' => $type,
                'status' => 'pending',
                'occurred_at' => $rating?->created_at ?? $book->created_at,
            ]);
            if ($activity->wasRecentlyCreated) {
                // The database queue shares the transaction; workers only see committed requests.
                PublishFeedActivity::dispatch($activity->id)->onConnection('database');
            }

            return $activity;
        });
    }

    public function resume(Book $book): void
    {
        $book->feedActivities()->whereIn('status', ['pending', 'failed'])->pluck('id')
            ->each(fn ($id) => PublishFeedActivity::dispatch($id)->onConnection('database'));
    }
}
