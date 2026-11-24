<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendBookToKindleRequest;
use App\Http\Resources\KindleDeliveryResource;
use App\Models\Book;
use App\Services\Kindle\QueueKindleDelivery;
use Illuminate\Http\JsonResponse;

class KindleDeliveryController extends Controller
{
    public function store(SendBookToKindleRequest $request, Book $book, QueueKindleDelivery $queue): JsonResponse
    {
        $delivery = $queue->handle($book, $request->validated('idempotency_key'));

        return (new KindleDeliveryResource($delivery))->response()->setStatusCode(202);
    }
}
