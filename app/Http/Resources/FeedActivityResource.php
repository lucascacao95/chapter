<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FeedActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'actor' => ['id' => $this->user->id, 'name' => $this->user->name],
            'book' => [
                'title' => $this->snapshot['title'],
                'authors' => $this->snapshot['authors'],
                'cover_url' => $this->cover_path ? route('api.feed.cover', ['activity' => $this->id], false) : null,
            ],
            'rating' => $this->snapshot['rating'],
            'occurred_at' => $this->occurred_at,
            'published_at' => $this->published_at,
            'can_withdraw' => $request->user()->id === $this->user_id,
        ];
    }
}
