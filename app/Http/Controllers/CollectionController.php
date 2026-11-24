<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveCollectionRequest;
use App\Http\Resources\CollectionResource;
use App\Models\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class CollectionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Collection::class);

        return CollectionResource::collection(
            $request->user()->collections()
                ->withCount(['books' => fn ($books) => $books->where('user_id', $request->user()->id)])
                ->orderByDesc('id')->paginate(25),
        )->additional(['meta' => [
            'total_books_count' => $request->user()->books()->count(),
            'uncollected_books_count' => $request->user()->books()->whereNull('collection_id')->count(),
        ]]);
    }

    public function store(SaveCollectionRequest $request): JsonResponse
    {
        $collection = $request->user()->collections()->create($request->safe()->only(['name']));

        return (new CollectionResource($collection))->response()->setStatusCode(201);
    }

    public function show(Collection $collection): CollectionResource
    {
        Gate::authorize('view', $collection);

        return new CollectionResource($collection);
    }

    public function update(SaveCollectionRequest $request, Collection $collection): CollectionResource
    {
        $collection->update($request->safe()->only(['name']));

        return new CollectionResource($collection);
    }

    public function destroy(Collection $collection): Response
    {
        Gate::authorize('delete', $collection);
        $collection->delete();

        return response()->noContent();
    }
}
