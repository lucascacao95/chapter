<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadBookRequest;
use App\Http\Resources\BookResource;
use App\Services\Books\UploadBook;
use Illuminate\Http\JsonResponse;

class BookUploadController extends Controller
{
    public function __invoke(UploadBookRequest $request, UploadBook $upload): JsonResponse
    {
        $book = $upload->handle($request->user(), $request->file('file'), $request->validated('collection_id'), $request->boolean('share_to_feed'));

        return (new BookResource($book))->response()->setStatusCode(201);
    }
}
