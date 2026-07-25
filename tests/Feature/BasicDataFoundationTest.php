<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Favorite;
use App\Models\Genre;
use App\Models\Review;
use App\Models\ReviewLike;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BasicDataFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeders_create_required_basic_data_counts(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(5, User::count());
        $this->assertSame(10, Genre::count());
        $this->assertSame(11, Book::count());
        $this->assertSame(32, Review::count());
        $this->assertSame(15, Favorite::count());
        $this->assertSame(24, ReviewLike::count());
        $this->assertDatabaseHas('users', [
            'name' => '山田太郎',
            'email' => 'yamada@example.com',
        ]);
        $this->assertDatabaseHas('genres', ['name' => '小説']);
        $this->assertDatabaseHas('genres', ['name' => '旅行']);
        $this->assertDatabaseHas('books', [
            'title' => '吾輩は猫である',
            'author' => '夏目漱石',
            'isbn' => '9784101010014',
            'published_date' => '1905-01-01',
            'image_url' => 'https://placehold.co/200x300/e2e8f0/475569?text=1',
        ]);
        $this->assertDatabaseHas('books', [
            'title' => 'コンテナ物語',
            'author' => 'マルク・レビンソン',
            'isbn' => '9784822251468',
            'published_date' => '2007-01-18',
            'image_url' => 'https://placehold.co/200x300/e2e8f0/475569?text=11',
        ]);
    }

    public function test_seeders_can_be_run_repeatedly_without_duplicates(): void
    {
        $this->seed(DatabaseSeeder::class);
        $password = User::where('email', 'yamada@example.com')->value('password');
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(5, User::count());
        $this->assertSame(10, Genre::count());
        $this->assertSame(11, Book::count());
        $this->assertSame(32, Review::count());
        $this->assertSame(15, Favorite::count());
        $this->assertSame(24, ReviewLike::count());
        $this->assertSame($password, User::where('email', 'yamada@example.com')->value('password'));
    }

    public function test_model_relations_return_expected_related_data(): void
    {
        $this->seed(DatabaseSeeder::class);

        $book = Book::where('isbn', '9784822251468')->firstOrFail();
        $user = User::where('email', 'yamada@example.com')->firstOrFail();
        $review = Review::whereBelongsTo($book)->whereBelongsTo($user)->firstOrFail();

        $this->assertTrue($book->user->is($user));
        $this->assertTrue($book->genres->contains('name', 'ビジネス'));
        $this->assertTrue($book->genres->contains('name', '歴史'));
        $this->assertSame(2, $book->reviews()->count());
        $this->assertTrue($review->book->is($book));
        $this->assertTrue($review->user->is($user));
        $this->assertGreaterThan(0, $review->likedByUsers()->count());
        $this->assertTrue($user->books->contains($book));
        $this->assertTrue($user->reviews->contains($review));
        $this->assertTrue($user->favoriteBooks()->exists());
        $this->assertTrue($user->likedReviews()->exists());
    }

    public function test_reviews_prevent_duplicate_user_and_book_pairs(): void
    {
        $this->seed(DatabaseSeeder::class);

        $review = Review::firstOrFail();

        $this->expectException(QueryException::class);

        Review::create([
            'user_id' => $review->user_id,
            'book_id' => $review->book_id,
            'rating' => 5,
            'comment' => '重複レビューです。',
        ]);
    }

    public function test_favorites_prevent_duplicate_user_and_book_pairs(): void
    {
        $this->seed(DatabaseSeeder::class);

        $favorite = Favorite::firstOrFail();

        $this->expectException(QueryException::class);

        Favorite::create([
            'user_id' => $favorite->user_id,
            'book_id' => $favorite->book_id,
            'created_at' => now(),
        ]);
    }

    public function test_review_likes_prevent_duplicate_user_and_review_pairs(): void
    {
        $this->seed(DatabaseSeeder::class);

        $reviewLike = ReviewLike::firstOrFail();

        $this->expectException(QueryException::class);

        ReviewLike::create([
            'user_id' => $reviewLike->user_id,
            'review_id' => $reviewLike->review_id,
            'created_at' => now(),
        ]);
    }

    public function test_book_delete_cascades_related_basic_data(): void
    {
        $this->seed(DatabaseSeeder::class);

        $book = Book::where('isbn', '9784822251468')->firstOrFail();
        $reviewIds = $book->reviews()->pluck('id');

        $book->delete();

        $this->assertDatabaseMissing('books', ['isbn' => '9784822251468']);
        $this->assertDatabaseCount('books', 10);
        $this->assertDatabaseMissing('book_genre', ['book_id' => $book->id]);
        $this->assertDatabaseMissing('favorites', ['book_id' => $book->id]);
        foreach ($reviewIds as $reviewId) {
            $this->assertDatabaseMissing('reviews', ['id' => $reviewId]);
            $this->assertDatabaseMissing('review_likes', ['review_id' => $reviewId]);
        }
    }

    public function test_user_delete_cascades_owned_and_related_basic_data(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = User::where('email', 'yamada@example.com')->firstOrFail();
        $bookIds = $user->books()->pluck('id');
        $reviewIds = $user->reviews()->pluck('id');

        $user->delete();

        $this->assertDatabaseMissing('users', ['email' => 'yamada@example.com']);
        foreach ($bookIds as $bookId) {
            $this->assertDatabaseMissing('books', ['id' => $bookId]);
            $this->assertDatabaseMissing('book_genre', ['book_id' => $bookId]);
            $this->assertDatabaseMissing('favorites', ['book_id' => $bookId]);
        }
        foreach ($reviewIds as $reviewId) {
            $this->assertDatabaseMissing('reviews', ['id' => $reviewId]);
            $this->assertDatabaseMissing('review_likes', ['review_id' => $reviewId]);
        }
        $this->assertDatabaseMissing('favorites', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('review_likes', ['user_id' => $user->id]);
    }
}
