<?php

namespace App\Jobs;

use App\Models\Book;
use App\Models\FeedActivity;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class PublishFeedActivity implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(public int $activityId) {}

    public function backoff(): array
    {
        return [10, 30];
    }

    public function handle(): void
    {
        $reference = FeedActivity::find($this->activityId);
        if (! $reference) {
            return;
        }
        $copied = null;
        try {
            DB::transaction(function () use ($reference, &$copied) {
                // Publication, withdrawal and deletion lock the book before its activities.
                $book = Book::whereKey($reference->book_id)->lockForUpdate()->first();
                $activity = FeedActivity::whereKey($this->activityId)->lockForUpdate()->first();
                if (! $book || ! $activity || in_array($activity->status, ['published', 'withdrawn'], true)) {
                    return;
                }
                $file = $book->file;
                if ($file && $file->processing_status !== 'completed') {
                    $activity->update(['status' => $file->processing_status === 'failed' ? 'failed' : 'pending']);

                    return;
                }
                $rating = $activity->bookRating;
                if ($activity->type === 'book_rated' && ! $rating) {
                    return;
                }
                if ($book->cover_path && $file) {
                    $disk = Storage::disk($file->disk);
                    $path = 'feed-covers/'.Str::uuid().'.jpg';
                    $copied = [$file->disk, $path];
                    if (! $disk->copy($book->cover_path, $path) || ! $disk->setVisibility($path, 'private')) {
                        throw new RuntimeException('feed_cover_copy_failed');
                    }
                    $activity->cover_disk = $file->disk;
                    $activity->cover_path = $path;
                }
                $activity->snapshot = [
                    'title' => $book->title,
                    'authors' => $book->authors,
                    'rating' => $rating ? ['value' => $rating->rating, 'review' => $rating->review] : null,
                ];
                $activity->status = 'published';
                $activity->published_at = now();
                $activity->save();
            });
        } catch (Throwable) {
            if ($copied) {
                DeleteBookObjects::dispatch($copied[0], [$copied[1]])->onConnection('database');
            }
            // Never serialize SQL bindings, comments, paths or parser output in failed_jobs.
            throw new RuntimeException('feed_publication_failed');
        }
    }

    public function failed(?Throwable $error): void
    {
        FeedActivity::whereKey($this->activityId)->whereIn('status', ['pending', 'failed'])->update(['status' => 'failed']);
    }
}
