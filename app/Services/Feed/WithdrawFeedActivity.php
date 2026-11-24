<?php

namespace App\Services\Feed;

use App\Jobs\DeleteBookObjects;
use App\Models\Book;
use App\Models\FeedActivity;
use Illuminate\Support\Facades\DB;

class WithdrawFeedActivity
{
    public function handle(FeedActivity $activity): void
    {
        DB::transaction(function () use ($activity) {
            Book::whereKey($activity->book_id)->lockForUpdate()->firstOrFail();
            $activity = FeedActivity::whereKey($activity->id)->lockForUpdate()->firstOrFail();
            if ($activity->status === 'withdrawn') {
                return;
            }
            $activity->update(['status' => 'withdrawn', 'withdrawn_at' => now()]);
            if ($activity->cover_disk && $activity->cover_path) {
                DeleteBookObjects::dispatch($activity->cover_disk, [$activity->cover_path])->onConnection('database');
            }
        });
    }
}
