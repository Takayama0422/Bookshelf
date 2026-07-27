<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;

class ReadingReportService
{
    /**
     * @return array{
     *     book_count: int,
     *     favorite_count: int,
     *     review_count: int,
     *     average_rating: float|null,
     *     rating_counts: array<int, int>,
     *     has_data: bool
     * }
     */
    public function summarizeFor(User $user): array
    {
        $bookCount = $user->books()->count();
        $favoriteCount = $user->favoriteBooks()->count();
        $reviewCount = $user->reviews()->count();
        $averageRating = $user->reviews()->avg('rating');
        $ratingCounts = $this->ratingCountsFor($user);

        return [
            'book_count' => $bookCount,
            'favorite_count' => $favoriteCount,
            'review_count' => $reviewCount,
            'average_rating' => $averageRating === null ? null : round((float) $averageRating, 2),
            'rating_counts' => $ratingCounts,
            'has_data' => $bookCount > 0 || $favoriteCount > 0 || $reviewCount > 0,
        ];
    }

    /**
     * @return array<int, int>
     */
    private function ratingCountsFor(User $user): array
    {
        /** @var Collection<int, int> $counts */
        $counts = $user->reviews()
            ->selectRaw('rating, count(*) as aggregate')
            ->groupBy('rating')
            ->pluck('aggregate', 'rating');

        $ratingCounts = [];

        for ($rating = 5; $rating >= 1; $rating--) {
            $ratingCounts[$rating] = (int) ($counts[$rating] ?? 0);
        }

        return $ratingCounts;
    }
}
