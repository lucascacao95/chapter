<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListFeedRequest;
use App\Http\Resources\FeedActivityResource;
use App\Models\FeedActivity;
use App\Services\Feed\WithdrawFeedActivity;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class FeedController extends Controller
{
    public function index(ListFeedRequest $request): AnonymousResourceCollection
    {
        return FeedActivityResource::collection(FeedActivity::visibleTo($request->user())->with('user:id,name')
            ->orderByDesc('published_at')->orderByDesc('id')->cursorPaginate(20));
    }

    public function destroy(FeedActivity $activity, WithdrawFeedActivity $withdraw): Response
    {
        Gate::authorize('delete', $activity);
        $withdraw->handle($activity);

        return response()->noContent();
    }
}
