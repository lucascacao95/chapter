<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookRatingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'feed_activity' => $this->whenLoaded('feedActivity', fn () => $this->feedActivity ? $this->feedActivity->only(['id', 'status']) : null),
            'book_id' => $this->book_id,
            'book_file_id' => $this->book_file_id,
            'user_id' => $this->user_id,
            'rating' => $this->rating,
            'review' => $this->review,
            'created_at' => $this->created_at,
        ];
    }
}
