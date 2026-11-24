<?php

namespace App\Policies;

use App\Models\Collection;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CollectionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, Collection $collection): Response
    {
        return $user->id === $collection->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function update(User $user, Collection $collection): Response
    {
        return $this->view($user, $collection);
    }

    public function delete(User $user, Collection $collection): Response
    {
        return $this->view($user, $collection);
    }
}
