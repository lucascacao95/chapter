<?php

namespace App\Services\Ratings;

use App\Models\Book;
use App\Models\BookRating;
use App\Services\Feed\RequestFeedPublication;
use Illuminate\Support\Facades\DB;

class RecordBookRating
{
    public function handle(Book $book, array $data): BookRating
    {
        return DB::transaction(function () use ($book, $data) {
            $current = Book::whereKey($book->id)->lockForUpdate()->firstOrFail();

            $rating = $current->ratings()->create([
                'user_id' => $current->user_id,
                'book_file_id' => $current->file()->value('id'),
                'rating' => $data['rating'],
                'review' => $data['review'] ?? null,
            ]);
            if ($data['share_to_feed'] ?? false) {
                app(RequestFeedPublication::class)->handle($current, $rating);
            }

            return $rating->load('feedActivity');
        });
    }
}
