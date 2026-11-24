<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookRatingRequest;
use App\Http\Resources\BookRatingResource;
use App\Models\Book;
use App\Services\Ratings\RecordBookRating;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class BookRatingController extends Controller
{
    public function index(Book $book): AnonymousResourceCollection
    {
        Gate::authorize('view', $book);

        return BookRatingResource::collection($book->ratings()->with('feedActivity')->orderByDesc('id')->paginate(25));
    }

    public function store(StoreBookRatingRequest $request, Book $book, RecordBookRating $record): JsonResponse
    {
        return (new BookRatingResource($record->handle($book, $request->validated())))
            ->response()->setStatusCode(201);
    }
}
