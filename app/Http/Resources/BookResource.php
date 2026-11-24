<?php

namespace App\Http\Resources;

use App\Models\Book;
use App\Services\Kindle\KindleConfiguration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Book */
class BookResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'feed_activity' => $this->whenLoaded('feedActivity', fn () => $this->feedActivity ? $this->feedActivity->only(['id', 'status']) : null),
            'title' => $this->title,
            'authors' => $this->authors,
            'page_count' => $this->page_count,
            'publisher' => $this->publisher,
            'edition' => $this->edition,
            'description' => $this->description,
            'language' => $this->language,
            'published_year' => $this->published_year,
            'isbn' => $this->isbn,
            'collection_id' => $this->collection_id,
            'latest_rating' => $this->whenLoaded('latestRating', fn () => $this->latestRating ? new BookRatingResource($this->latestRating) : null),
            'kindle' => [
                'configured' => app(KindleConfiguration::class)->ready(),
                'max_attachment_bytes' => config('kindle.max_attachment_bytes'),
                'delivery' => $this->relationLoaded('file') && $this->file?->relationLoaded('latestKindleDelivery')
                    ? ($this->file->latestKindleDelivery ? new KindleDeliveryResource($this->file->latestKindleDelivery) : null)
                    : null,
            ],
            'cover_url' => $this->cover_path ? route('api.books.cover', ['book' => $this->id], false) : null,
            'file' => $this->whenLoaded('file', fn () => $this->file ? [
                'id' => $this->file->id,
                'original_name' => $this->file->original_name,
                'format' => $this->file->format,
                'size_bytes' => $this->file->size_bytes,
                'processing_status' => $this->file->processing_status,
                'processing_error_code' => $this->file->processing_error_code,
                'processed_at' => $this->file->processed_at,
            ] : null),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
