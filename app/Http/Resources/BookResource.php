<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'title' => $this->title,
            'author' => $this->author,
            'isbn' => $this->isbn,
            'published_date' => $this->published_date?->toDateString(),
            'description' => $this->description,
            'image_url' => $this->image_url,
            'average_rating' => $this->when(
                array_key_exists('reviews_avg_rating', $this->resource->getAttributes()),
                fn () => $this->reviews_avg_rating === null ? null : round((float) $this->reviews_avg_rating, 1)
            ),
            'review_count' => (int) ($this->reviews_count ?? 0),
            'genres' => GenreResource::collection($this->whenLoaded('genres')),
            'reviews' => ReviewResource::collection($this->whenLoaded('reviews')),
        ];
    }
}
