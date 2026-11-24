<?php

namespace App\Services\Feed;

use App\Models\BookRating;
use App\Models\User;
use Illuminate\Support\Collection;

class RecentBooks
{
    public function forUser(User $user): Collection
    {
        $ratings = BookRating::selectRaw('book_id, MAX(created_at) AS rated_at')->where('user_id', $user->id)->groupBy('book_id');

        return $user->books()->leftJoinSub($ratings, 'recent_ratings', 'recent_ratings.book_id', '=', 'books.id')
            ->select('books.*')->selectRaw('CASE WHEN recent_ratings.rated_at > books.updated_at THEN recent_ratings.rated_at ELSE books.updated_at END AS recent_activity_at')
            ->orderByDesc('recent_activity_at')->orderByDesc('books.id')->limit(3)->get()
            ->map(fn ($book) => [
                'id' => $book->id, 'title' => $book->title, 'authors' => $book->authors,
                'cover_url' => $book->cover_path ? route('api.books.cover', ['book' => $book->id], false) : null,
                'recent_activity_at' => $book->recent_activity_at,
            ]);
    }
}
