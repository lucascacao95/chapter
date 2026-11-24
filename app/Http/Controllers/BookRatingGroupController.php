<?php

namespace App\Http\Controllers;

use App\Models\BookRating;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookRatingGroupController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        // Join the latest row by its book so historical versions cannot multiply the counts.
        $latest = BookRating::selectRaw('book_id, MAX(id) AS id')->where('user_id', $request->user()->id)->groupBy('book_id');
        $counts = $request->user()->books()
            ->leftJoinSub($latest, 'latest', 'latest.book_id', '=', 'books.id')
            ->leftJoin('book_ratings as current_rating', 'current_rating.id', '=', 'latest.id')
            ->selectRaw('CASE WHEN current_rating.rating IS NULL THEN -1 ELSE CAST(FLOOR(current_rating.rating) AS INTEGER) END AS band, COUNT(*) AS total')
            ->groupBy('band')->pluck('total', 'band');

        $labels = ['5' => '5 estrelas', '4' => '4 a 4,5 estrelas', '3' => '3 a 3,5 estrelas', '2' => '2 a 2,5 estrelas', '1' => '1 a 1,5 estrelas', '0' => '0 a 0,5 estrela', 'none' => 'Sem avaliação'];
        $groups = [];
        foreach ($labels as $key => $label) {
            $groups[] = ['key' => (string) $key, 'label' => $label, 'books_count' => (int) ($counts[$key === 'none' ? -1 : $key] ?? 0)];
        }

        return response()->json(['data' => $groups, 'meta' => ['total_books_count' => (int) $counts->sum()]]);
    }
}
