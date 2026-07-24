<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RatingRankingTest extends TestCase
{
    use RefreshDatabase;

    private static int $isbnSequence = 1;

    public function test_guests_can_view_rating_ranking(): void
    {
        $topBook = $this->createBook(['title' => '評価トップの書籍']);
        $secondBook = $this->createBook(['title' => '評価二位の書籍']);

        $this->createReview($topBook, 5);
        $this->createReview($secondBook, 4);

        $this->get(route('ranking.index'))
            ->assertOk()
            ->assertSee('評価ランキング TOP 10')
            ->assertSee('評価トップの書籍')
            ->assertSee('評価二位の書籍');
    }

    public function test_rating_ranking_is_ordered_by_average_rating_review_count_and_book_id(): void
    {
        $highRatedBook = $this->createBook(['title' => '平均評価が一番高い書籍']);
        $fewReviewedBook = $this->createBook(['title' => '同点でレビュー数が少ない書籍']);
        $manyReviewedBook = $this->createBook(['title' => '同点でレビュー数が多い書籍']);
        $sameScoreOlderBook = $this->createBook(['title' => '同点同数でIDが小さい書籍']);
        $sameScoreNewerBook = $this->createBook(['title' => '同点同数でIDが大きい書籍']);
        $lowerRatedBook = $this->createBook(['title' => '平均評価が低い書籍']);

        $this->createReview($lowerRatedBook, 3);
        $this->createReview($fewReviewedBook, 5);
        $this->createReview($manyReviewedBook, 5);
        $this->createReview($manyReviewedBook, 5);
        $this->createReview($sameScoreNewerBook, 4);
        $this->createReview($sameScoreOlderBook, 4);
        $this->createReview($highRatedBook, 5);
        $this->createReview($highRatedBook, 4);

        $response = $this->get(route('ranking.index'));

        $response->assertOk()
            ->assertSeeInOrder([
                '同点でレビュー数が多い書籍',
                '同点でレビュー数が少ない書籍',
                '平均評価が一番高い書籍',
                '同点同数でIDが小さい書籍',
                '同点同数でIDが大きい書籍',
                '平均評価が低い書籍',
            ]);
    }

    public function test_books_without_reviews_are_displayed_after_reviewed_books(): void
    {
        $reviewedBook = $this->createBook(['title' => 'レビューあり書籍']);
        $unreviewedBook = $this->createBook(['title' => 'レビューなし書籍']);

        $this->createReview($reviewedBook, 5);

        $this->get(route('ranking.index'))
            ->assertOk()
            ->assertSeeInOrder([
                'レビューあり書籍',
                'レビューなし書籍',
            ]);

        $this->assertDatabaseHas('books', ['id' => $unreviewedBook->id]);
    }

    public function test_rating_ranking_displays_top_ten_books(): void
    {
        for ($i = 1; $i <= 11; $i++) {
            $book = $this->createBook([
                'title' => sprintf('ランキング書籍%02d', $i),
            ]);
            $this->createReview($book, $i === 1 ? 1 : 5);
        }

        $this->get(route('ranking.index'))
            ->assertOk()
            ->assertSee('ランキング書籍11')
            ->assertSee('ランキング書籍02')
            ->assertDontSee('ランキング書籍01');
    }

    public function test_rating_ranking_shows_empty_message_when_no_books_exist(): void
    {
        $this->get(route('ranking.index'))
            ->assertOk()
            ->assertSee('まだレビューが投稿された書籍がありません。');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createBook(array $overrides = []): Book
    {
        $user = User::factory()->create();
        $genre = Genre::create(['name' => '文学'.uniqid()]);

        $book = Book::create(array_merge([
            'user_id' => $user->id,
            'title' => 'テスト書籍',
            'author' => 'テスト著者',
            'isbn' => '9784'.str_pad((string) self::$isbnSequence++, 9, '0', STR_PAD_LEFT),
            'published_date' => '2020-01-01',
            'description' => '説明文です。',
            'image_url' => null,
        ], $overrides));

        $book->genres()->sync([$genre->id]);

        return $book;
    }

    private function createReview(Book $book, int $rating): Review
    {
        return Review::create([
            'user_id' => User::factory()->create()->id,
            'book_id' => $book->id,
            'rating' => $rating,
            'comment' => 'ランキング確認用レビューです。',
        ]);
    }
}
