<?php

namespace App\Observers;

use App\Models\User;

class UserObserver
{
    public function deleting(User $user): void
    {
        // Preserve object references before the database cascade removes file records.
        $books = $user->books()->orderBy('id')->lockForUpdate()->get();
        $books->load(['file', 'feedActivities']);
        $user->setRelation('books', $books);
    }

    public function deleted(User $user): void
    {
        foreach ($user->getRelation('books') as $book) {
            (new BookObserver)->deleted($book);
        }
    }
}
