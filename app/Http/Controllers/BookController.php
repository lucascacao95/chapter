<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListBooksRequest;
use App\Http\Requests\SaveBookRequest;
use App\Http\Resources\BookResource;
use App\Jobs\ExtractBookMetadata;
use App\Models\Book;
use App\Services\Feed\RequestFeedPublication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class BookController extends Controller
{
    public function index(ListBooksRequest $request): AnonymousResourceCollection
    {
        $books = $request->user()->books()->with(['file.latestKindleDelivery', 'latestRating.feedActivity', 'feedActivity'])->orderByDesc('id');
        $filter = $request->validated('collection_id');
        if ($filter === 'none') {
            $books->whereNull('collection_id');
        } elseif ($filter !== null) {
            $books->where('collection_id', $filter);
        }

        $band = $request->validated('rating_band');
        if ($band === 'none') {
            $books->whereDoesntHave('latestRating');
        } elseif ($band !== null) {
            $books->whereHas('latestRating', fn ($query) => $query->whereBetween('rating', [(int) $band, min(5, (int) $band + 0.5)]));
        }

        return BookResource::collection($books->paginate(25)->withQueryString());
    }

    public function store(SaveBookRequest $request): JsonResponse
    {
        $book = DB::transaction(function () use ($request) {
            $book = $request->user()->books()->create($request->safe()->except('share_to_feed'));
            if ($request->boolean('share_to_feed')) {
                app(RequestFeedPublication::class)->handle($book);
            }

            return $book;
        });

        return (new BookResource($book->refresh()->load(['file.latestKindleDelivery', 'latestRating.feedActivity', 'feedActivity'])))->response()->setStatusCode(201);
    }

    public function show(Book $book): BookResource
    {
        Gate::authorize('view', $book);

        return new BookResource($book->load(['file.latestKindleDelivery', 'latestRating.feedActivity', 'feedActivity']));
    }

    public function update(SaveBookRequest $request, Book $book): BookResource
    {
        DB::transaction(function () use ($book, $request) {
            $book = Book::whereKey($book->id)->lockForUpdate()->firstOrFail();
            $book->update($request->validated());
            $file = $book->file()->lockForUpdate()->first();
            if ($file) {
                $fields = array_intersect(array_keys($request->validated()), ExtractBookMetadata::FIELDS);
                $file->update(['metadata_overrides' => array_values(array_unique(array_merge($file->metadata_overrides ?? [], $fields)))]);
            }
        });
        $book->refresh();

        return new BookResource($book->load(['file.latestKindleDelivery', 'latestRating.feedActivity', 'feedActivity']));
    }

    public function destroy(Book $book): Response
    {
        Gate::authorize('delete', $book);
        DB::transaction(fn () => $book->delete());

        return response()->noContent();
    }
}
