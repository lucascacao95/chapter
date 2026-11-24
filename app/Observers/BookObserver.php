<?php

namespace App\Observers;

use App\Jobs\DeleteBookObjects;
use App\Models\Book;

class BookObserver
{
    public function deleting(Book $book): void
    {
        Book::whereKey($book->id)->lockForUpdate()->first();
        $book->load(['file', 'feedActivities']);
    }

    public function deleted(Book $book): void
    {
        foreach ($book->getRelation('feedActivities') as $activity) {
            if ($activity->cover_disk && $activity->cover_path) {
                DeleteBookObjects::dispatch($activity->cover_disk, [$activity->cover_path])->onConnection('database');
            }
        }
        if ($file = $book->getRelation('file')) {
            DeleteBookObjects::dispatch($file->disk, array_values(array_filter([$file->storage_key, $book->cover_path])))->onConnection('database');
        }
    }
}
