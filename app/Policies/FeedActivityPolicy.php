<?php

namespace App\Policies;

use App\Models\FeedActivity;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class FeedActivityPolicy
{
    public function view(User $user, FeedActivity $activity): Response
    {
        return FeedActivity::visibleTo($user)->whereKey($activity->id)->exists()
            ? Response::allow() : Response::denyAsNotFound();
    }

    public function delete(User $user, FeedActivity $activity): Response
    {
        return $activity->user_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }
}
