<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReadingReportTest extends TestCase
{
    use RefreshDatabase;

    private int $isbnSequence = 1;

    public function test_authenticated_user_can_view_own_reading_report(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('reading-report.show'))
            ->assertOk()
            ->assertSee('マイ読書レポート')
            ->assertSee('登録した書籍')
            ->assertSee('レビュー傾向');
    }

    public function test_guest_cannot_view_reading_report(): void
    {
        $this->get(route('reading-report.show'))
            ->assertRedirect('/login');
    }

    public function test_other_users_data_is_not_included_in_report(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $ownBook = $this->createBook($user);
        $otherBook = $this->createBook($otherUser);

        $this->createReview($user, $ownBook, 5);
        $this->createReview($otherUser, $otherBook, 1);
        $user->toggleFavorite($ownBook);
        $otherUser->toggleFavorite($otherBook);

        $this->actingAs($user)
            ->get(route('reading-report.show'))
            ->assertOk()
            ->assertSee('1件のレビューをもとに集計しています。')
            ->assertSee('5.00')
            ->assertSee('評価5')
            ->assertSee('1件')
            ->assertDontSee('1.00');
    }

    public function test_multiple_books_and_reviews_are_aggregated_correctly(): void
    {
        $user = User::factory()->create();
        $firstBook = $this->createBook($user);
        $secondBook = $this->createBook($user);
        $favoriteBook = $this->createBook(User::factory()->create());

        $this->createReview($user, $firstBook, 5);
        $this->createReview($user, $secondBook, 3);
        $user->toggleFavorite($firstBook);
        $user->toggleFavorite($favoriteBook);

        $this->actingAs($user)
            ->get(route('reading-report.show'))
            ->assertOk()
            ->assertSee('登録した書籍')
            ->assertSee('2')
            ->assertSee('お気に入り')
            ->assertSee('2')
            ->assertSee('投稿したレビュー')
            ->assertSee('2')
            ->assertSee('4.00')
            ->assertSee('2件のレビューをもとに集計しています。')
            ->assertSee('評価5')
            ->assertSee('評価3');
    }

    public function test_report_displays_safely_when_user_has_no_books_or_reviews(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('reading-report.show'))
            ->assertOk()
            ->assertSee('まだ読書レポートに表示できるデータがありません。')
            ->assertSee('0件のレビューをもとに集計しています。')
            ->assertSee('評価5')
            ->assertSee('-');
    }

    public function test_average_rating_and_counts_follow_report_specification(): void
    {
        $user = User::factory()->create();
        $firstBook = $this->createBook($user);
        $secondBook = $this->createBook($user);
        $thirdBook = $this->createBook($user);

        $this->createReview($user, $firstBook, 5);
        $this->createReview($user, $secondBook, 4);
        $this->createReview($user, $thirdBook, 4);

        $this->actingAs($user)
            ->get(route('reading-report.show'))
            ->assertOk()
            ->assertSee('4.33')
            ->assertSee('3件のレビューをもとに集計しています。')
            ->assertSee('評価5')
            ->assertSee('1件')
            ->assertSee('評価4')
            ->assertSee('2件');
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
                $user->toggleFavorite($book);
            }

            DB::flushQueryLog();
            DB::enableQueryLog();

            $this->actingAs($user)
                ->get(route('reading-report.show'))
                ->assertOk();

            $queryCounts[] = count(DB::getQueryLog());
            DB::disableQueryLog();
        }

        $this->assertSame($queryCounts[0], $queryCounts[1]);
    }

    private function createBook(User $user): Book
    {
        $genre = Genre::create(['name' => 'レポートジャンル'.uniqid()]);

        $book = Book::create([
            'user_id' => $user->id,
            'title' => 'レポート確認書籍'.$this->isbnSequence,
            'author' => 'レポート著者',
            'isbn' => '9784'.str_pad((string) $this->isbnSequence++, 9, '0', STR_PAD_LEFT),
            'published_date' => '2020-01-01',
            'description' => 'レポート確認用の説明文です。',
            'image_url' => null,
        ]);

        $book->genres()->sync([$genre->id]);

        return $book;
    }

    private function createReview(User $user, Book $book, int $rating): Review
    {
        return Review::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'rating' => $rating,
            'comment' => 'レポート確認用レビューです。',
        ]);
    }
}
