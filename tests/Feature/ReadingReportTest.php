<?php

namespace Tests\Feature;

use App\Enums\ReadingPlanStatus;
use App\Models\Book;
use App\Models\Genre;
use App\Models\ReadingPlan;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReadingReportTest extends TestCase
{
    use RefreshDatabase;

    private int $isbnSequence = 1;

    public function test_reports_route_requires_authentication_and_authenticated_user_can_view_it(): void
    {
        $user = User::factory()->create();

        $this->assertSame('/reports', route('reading-report.show', [], false));

        $this->get('/reports')
            ->assertRedirect('/login');

        $this->actingAs($user)
            ->get('/reports')
            ->assertOk()
            ->assertSee('マイ読書レポート')
            ->assertSee('総レビュー数')
            ->assertSee('読了冊数')
            ->assertSee('評価分布')
            ->assertSee('高評価書籍TOP5')
            ->assertSee('ジャンル別評価傾向TOP5')
            ->assertDontSee('登録した書籍');
    }

    public function test_report_aggregates_only_authenticated_users_reviews_and_reading_plans(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $genre = Genre::create(['name' => '対象ジャンル']);
        $ownBook = $this->createBook($user, [$genre]);
        $secondOwnBook = $this->createBook($user, [$genre]);
        $otherBook = $this->createBook($otherUser);

        $this->createReview($user, $ownBook, 5);
        $this->createReview($user, $secondOwnBook, 3);
        $this->createReview($otherUser, $otherBook, 1);
        $this->createReadingPlan($user, $ownBook, ReadingPlanStatus::COMPLETED);
        $this->createReadingPlan($otherUser, $otherBook, ReadingPlanStatus::COMPLETED);

        $this->actingAs($user)
            ->get('/reports')
            ->assertOk()
            ->assertViewHas('report', function (array $report) use ($ownBook, $genre): bool {
                return $report['review_count'] === 2
                    && $report['completed_book_count'] === 1
                    && $report['average_rating'] === 4.0
                    && $report['rating_counts'] === [5 => 1, 4 => 0, 3 => 1, 2 => 0, 1 => 0]
                    && $report['top_rated_books']->pluck('book_id')->all() === [$ownBook->id]
                    && $report['genre_trends']->pluck('genre.id')->all() === [$genre->id]
                    && $report['genre_trends']->pluck('average_rating')->all() === [4.0]
                    && $report['genre_trends']->pluck('review_count')->all() === [2];
            })
            ->assertSee('4.00')
            ->assertDontSee($otherBook->title);
    }

    public function test_completed_book_count_uses_unique_completed_reading_plan_book_ids(): void
    {
        $user = User::factory()->create();
        $firstBook = $this->createBook($user);
        $secondBook = $this->createBook($user);

        $this->createReadingPlan($user, $firstBook, ReadingPlanStatus::COMPLETED);
        $this->createReadingPlan($user, $firstBook, ReadingPlanStatus::COMPLETED);
        $this->createReadingPlan($user, $secondBook, ReadingPlanStatus::IN_PROGRESS);

        $this->actingAs($user)
            ->get('/reports')
            ->assertOk()
            ->assertViewHas(
                'report',
                fn (array $report): bool => $report['completed_book_count'] === 1 && $report['has_data'] === true
            )
            ->assertDontSee('まだ読書レポートに表示できるデータがありません。');
    }

    public function test_high_rated_books_are_filtered_sorted_limited_and_linked(): void
    {
        $user = User::factory()->create();
        $ratings = [5, 4, 5, 3, 5, 4, 4];
        $books = [];

        foreach ($ratings as $rating) {
            $book = $this->createBook($user);
            $books[] = $book;
            $this->createReview($user, $book, $rating);
        }

        $expectedBookIds = [
            $books[0]->id,
            $books[2]->id,
            $books[4]->id,
            $books[1]->id,
            $books[5]->id,
        ];

        $this->actingAs($user)
            ->get('/reports')
            ->assertOk()
            ->assertViewHas(
                'report',
                fn (array $report): bool => $report['top_rated_books']->pluck('book_id')->all() === $expectedBookIds
            )
            ->assertSee(route('books.show', $books[0]), false)
            ->assertDontSee(route('books.show', $books[3]), false)
            ->assertDontSee(route('books.show', $books[6]), false);
    }

    public function test_genre_trends_include_multiple_genres_and_follow_required_order_and_limit(): void
    {
        $user = User::factory()->create();
        $genres = collect(['A', 'B', 'C', 'D', 'E', 'F'])
            ->map(fn (string $name): Genre => Genre::create(['name' => 'ジャンル'.$name]));

        $multipleGenreBook = $this->createBook($user, [$genres[0], $genres[1]]);
        $this->createReview($user, $multipleGenreBook, 5);
        $this->createReview($user, $this->createBook($user, [$genres[0]]), 5);
        $this->createReview($user, $this->createBook($user, [$genres[2]]), 4);
        $this->createReview($user, $this->createBook($user, [$genres[3]]), 4);
        $this->createReview($user, $this->createBook($user, [$genres[4]]), 3);
        $this->createReview($user, $this->createBook($user, [$genres[5]]), 2);

        $response = $this->actingAs($user)
            ->get('/reports')
            ->assertOk()
            ->assertViewHas('report', function (array $report) use ($genres): bool {
                return $report['genre_trends']->pluck('genre.id')->all() === [
                    $genres[0]->id,
                    $genres[1]->id,
                    $genres[2]->id,
                    $genres[3]->id,
                    $genres[4]->id,
                ]
                    && $report['genre_trends']->pluck('average_rating')->all() === [5.0, 5.0, 4.0, 4.0, 3.0]
                    && $report['genre_trends']->pluck('review_count')->all() === [2, 1, 1, 1, 1];
            });

        foreach ($genres->take(5) as $genre) {
            $response->assertSee(route('genres.show', $genre), false);
        }

        $response->assertDontSee(route('genres.show', $genres[5]), false);
    }

    public function test_report_displays_zero_unrated_and_empty_messages_without_data(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get('/reports')
            ->assertOk()
            ->assertViewHas('report', function (array $report): bool {
                return $report['review_count'] === 0
                    && $report['completed_book_count'] === 0
                    && $report['average_rating'] === null
                    && $report['rating_counts'] === [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0]
                    && $report['top_rated_books']->isEmpty()
                    && $report['genre_trends']->isEmpty()
                    && $report['has_data'] === false;
            })
            ->assertSee('まだ読書レポートに表示できるデータがありません。')
            ->assertSee('0件のレビューをもとに集計')
            ->assertSee('-');

        $this->assertSame(2, substr_count($response->getContent(), '該当データなし'));
    }

    public function test_reading_report_does_not_issue_queries_per_book_or_review(): void
    {
        $queryCounts = [];

        foreach ([1, 5] as $bookTotal) {
            $this->refreshDatabase();

            $user = User::factory()->create();

            for ($i = 0; $i < $bookTotal; $i++) {
                $book = $this->createBook($user);
                $this->createReview($user, $book, ($i % 5) + 1);
                $this->createReadingPlan($user, $book, ReadingPlanStatus::COMPLETED);
            }

            DB::flushQueryLog();
            DB::enableQueryLog();

            $this->actingAs($user)
                ->get('/reports')
                ->assertOk();

            $queryCounts[] = count(DB::getQueryLog());
            DB::disableQueryLog();
        }

        $this->assertSame($queryCounts[0], $queryCounts[1]);
    }

    /**
     * レポートテスト用の書籍を作成し、指定ジャンルを関連付ける。
     *
     * @param  User  $user  書籍の登録者
     * @param  array<int, Genre>  $genres  関連付けるジャンル
     */
    private function createBook(User $user, array $genres = []): Book
    {
        if ($genres === []) {
            $genres = [Genre::create(['name' => 'レポートジャンル'.uniqid()])];
        }

        $book = Book::create([
            'user_id' => $user->id,
            'title' => 'レポート確認書籍'.$this->isbnSequence,
            'author' => 'レポート著者',
            'isbn' => '9784'.str_pad((string) $this->isbnSequence++, 9, '0', STR_PAD_LEFT),
            'published_date' => '2020-01-01',
            'description' => 'レポート確認用の説明文です。',
            'image_url' => null,
        ]);

        $book->genres()->sync(collect($genres)->pluck('id')->all());

        return $book;
    }

    /**
     * レポートテスト用のレビューを作成する。
     *
     * @param  User  $user  レビュー投稿者
     * @param  Book  $book  レビュー対象書籍
     * @param  int  $rating  1から5の評価
     */
    private function createReview(User $user, Book $book, int $rating): Review
    {
        return Review::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'rating' => $rating,
            'comment' => 'レポート確認用レビューです。',
        ]);
    }

    /**
     * レポートテスト用の読書計画を作成する。
     *
     * @param  User  $user  読書計画の所有者
     * @param  Book  $book  対象書籍
     * @param  ReadingPlanStatus  $status  読書計画の状態
     */
    private function createReadingPlan(User $user, Book $book, ReadingPlanStatus $status): ReadingPlan
    {
        return ReadingPlan::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'target_date' => now()->addWeek()->toDateString(),
            'status' => $status->value,
            'completed_at' => $status === ReadingPlanStatus::COMPLETED ? now() : null,
        ]);
    }
}
