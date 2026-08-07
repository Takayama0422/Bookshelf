<?php

namespace App\Services;

use App\Enums\ReadingPlanStatus;
use App\Models\Genre;
use App\Models\ReadingPlan;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Collection;

class ReadingReportService
{
    /**
     * ユーザーのレビューと読了済み読書計画を集計して読書レポートを生成する。
     *
     * @param  User  $user  集計対象のユーザー
     * @return array{
     *     review_count: int,
     *     completed_book_count: int,
     *     average_rating: float|null,
     *     rating_counts: array<int, int>,
     *     top_rated_books: Collection<int, Review>,
     *     genre_trends: Collection<int, array{genre: Genre, average_rating: float, review_count: int}>,
     *     has_data: bool
     * }
     */
    public function summarizeFor(User $user): array
    {
        /** @var Collection<int, Review> $reviews */
        $reviews = $user->reviews()
            ->with('book.genres')
            ->get();

        /** @var Collection<int, ReadingPlan> $completedPlans */
        $completedPlans = $user->readingPlans()
            ->where('status', ReadingPlanStatus::Completed->value)
            ->get(['book_id']);

        $averageRating = $reviews->avg('rating');

        return [
            'review_count' => $reviews->count(),
            'completed_book_count' => $completedPlans->pluck('book_id')->unique()->count(),
            'average_rating' => $averageRating === null ? null : round((float) $averageRating, 2),
            'rating_counts' => $this->ratingCountsFor($reviews),
            'top_rated_books' => $this->topRatedBooksFor($reviews),
            'genre_trends' => $this->genreTrendsFor($reviews),
            'has_data' => $reviews->isNotEmpty() || $completedPlans->isNotEmpty(),
        ];
    }

    /**
     * ユーザーのレビュー件数を評価5から1まで集計し、該当なしの評価も0件で返す。
     *
     * @param  Collection<int, Review>  $reviews  集計対象のレビュー
     * @return array<int, int>
     */
    private function ratingCountsFor(Collection $reviews): array
    {
        /** @var Collection<int, int> $counts */
        $counts = $reviews->countBy('rating');

        return collect(range(5, 1))
            ->mapWithKeys(fn (int $rating): array => [
                $rating => (int) ($counts[$rating] ?? 0),
            ])
            ->all();
    }

    /**
     * 評価4以上のレビューを評価降順、書籍ID昇順で最大5件返す。
     *
     * @param  Collection<int, Review>  $reviews  集計対象のレビュー
     * @return Collection<int, Review>
     */
    private function topRatedBooksFor(Collection $reviews): Collection
    {
        return $reviews
            ->filter(fn (Review $review): bool => $review->rating >= 4)
            ->sort(function (Review $first, Review $second): int {
                return ($second->rating <=> $first->rating)
                    ?: ($first->book_id <=> $second->book_id);
            })
            ->take(5)
            ->values();
    }

    /**
     * レビュー対象書籍のジャンルごとに平均評価とレビュー件数を集計し、上位5件を返す。
     *
     * 複数ジャンルの書籍は各ジャンルへ1件ずつ計上する。
     *
     * @param  Collection<int, Review>  $reviews  集計対象のレビュー
     * @return Collection<int, array{genre: Genre, average_rating: float, review_count: int}>
     */
    private function genreTrendsFor(Collection $reviews): Collection
    {
        return $reviews
            ->flatMap(function (Review $review): Collection {
                return $review->book->genres->map(fn (Genre $genre): array => [
                    'genre' => $genre,
                    'rating' => $review->rating,
                ]);
            })
            ->groupBy(fn (array $entry): int => $entry['genre']->id)
            ->map(function (Collection $entries): array {
                /** @var Genre $genre */
                $genre = $entries->first()['genre'];

                return [
                    'genre' => $genre,
                    'average_rating' => round((float) $entries->avg('rating'), 2),
                    'review_count' => $entries->count(),
                ];
            })
            ->sort(function (array $first, array $second): int {
                return ($second['average_rating'] <=> $first['average_rating'])
                    ?: ($second['review_count'] <=> $first['review_count'])
                    ?: ($first['genre']->id <=> $second['genre']->id);
            })
            ->take(5)
            ->values();
    }
}
