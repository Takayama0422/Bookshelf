<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Favorite;
use App\Models\Genre;
use App\Models\Review;
use App\Models\ReviewLike;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PublicBookApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_book_index_returns_paginated_books_with_genres_average_rating_and_review_count(): void
    {
        $genre = Genre::create(['name' => '文学']);
        $book = $this->createBook(null, [
            'title' => '検索できる本',
            'isbn' => '9784000000001',
        ], [$genre->id]);
        $this->createBook(null, [
            'title' => '別の本',
            'isbn' => '9784000000002',
        ]);
        $this->createReview($book, ['rating' => 5]);
        $this->createReview($book, ['rating' => 3], User::factory()->create());

        $this->getJson('/api/v1/books?keyword=検索&genre='.$genre->id.'&per_page=20')
            ->assertOk()
            ->assertJsonPath('data.0.id', $book->id)
            ->assertJsonPath('data.0.genres.0.name', '文学')
            ->assertJsonPath('data.0.average_rating', 4)
            ->assertJsonPath('data.0.review_count', 2)
            ->assertJsonPath('meta.per_page', 20)
            ->assertJsonCount(1, 'data');
    }

    public function test_book_index_validates_query_parameters(): void
    {
        $this->getJson('/api/v1/books?genre=999&sort=invalid&page=0&per_page=101')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['genre', 'sort', 'page', 'per_page'])
            ->assertJsonPath('message', '指定されたジャンルは存在しません。 (and 3 more errors)');
    }

    public function test_book_detail_returns_genres_and_reviews(): void
    {
        $genre = Genre::create(['name' => '技術書']);
        $book = $this->createBook(null, [], [$genre->id]);
        $reviewer = User::factory()->create(['name' => 'レビュー投稿者']);
        $review = $this->createReview($book, [
            'rating' => 5,
            'comment' => 'とても参考になりました。',
        ], $reviewer);

        $this->getJson('/api/v1/books/'.$book->id)
            ->assertOk()
            ->assertJsonPath('data.id', $book->id)
            ->assertJsonPath('data.genres.0.name', '技術書')
            ->assertJsonPath('data.reviews.0.id', $review->id)
            ->assertJsonPath('data.reviews.0.user.name', 'レビュー投稿者')
            ->assertJsonPath('data.reviews.0.rating', 5)
            ->assertJsonPath('data.reviews.0.comment', 'とても参考になりました。');
    }

    public function test_book_detail_returns_json_error_for_missing_book(): void
    {
        $this->getJson('/api/v1/books/999')
            ->assertNotFound()
            ->assertJsonPath('message', '書籍が見つかりません。')
            ->assertJsonPath('errors.book.0', '指定された書籍が見つかりません。');
    }

    public function test_book_store_creates_book_with_genres(): void
    {
        $user = User::factory()->create();
        $genre = Genre::create(['name' => '文学']);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/books', $this->validBookPayload($user, ['genre_ids' => [$genre->id]]))
            ->assertCreated()
            ->assertJsonPath('data.title', 'APIテスト書籍')
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.genres.0.id', $genre->id);

        $book = Book::firstOrFail();
        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'user_id' => $user->id,
            'isbn' => '9784000000100',
        ]);
        $this->assertDatabaseHas('book_genre', [
            'book_id' => $book->id,
            'genre_id' => $genre->id,
        ]);
    }

    public function test_book_store_accepts_nullable_isbn_and_published_date(): void
    {
        $user = User::factory()->create();
        $genre = Genre::create(['name' => '文学']);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/books', $this->validBookPayload($user, [
            'isbn' => null,
            'published_date' => null,
            'genre_ids' => [$genre->id],
        ]))
            ->assertCreated()
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.isbn', null)
            ->assertJsonPath('data.published_date', null);

        $this->assertDatabaseHas('books', [
            'user_id' => $user->id,
            'isbn' => null,
            'published_date' => null,
        ]);
    }

    public function test_book_store_returns_japanese_validation_errors(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/books', [
            'title' => '',
            'author' => '',
            'isbn' => '123',
            'published_date' => now()->addDay()->toDateString(),
            'genre_ids' => [],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'author', 'isbn', 'published_date', 'genre_ids'])
            ->assertJsonPath('errors.title.0', 'タイトルは必ず入力してください。');
    }

    public function test_book_update_updates_book_and_excludes_own_isbn_from_unique_rule(): void
    {
        $user = User::factory()->create();
        $genre = Genre::create(['name' => '文学']);
        $book = $this->createBook($user, ['isbn' => '9784000000100'], [$genre->id]);

        Sanctum::actingAs($user);

        $this->putJson('/api/v1/books/'.$book->id, $this->validBookPayload($user, [
            'title' => '更新後タイトル',
            'isbn' => '9784000000100',
            'genre_ids' => [$genre->id],
        ]))
            ->assertOk()
            ->assertJsonPath('data.id', $book->id)
            ->assertJsonPath('data.title', '更新後タイトル');

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'title' => '更新後タイトル',
            'isbn' => '9784000000100',
        ]);
    }

    public function test_book_update_returns_validation_and_missing_errors(): void
    {
        $book = $this->createBook();

        Sanctum::actingAs($book->user);

        $this->putJson('/api/v1/books/'.$book->id, [
            'user_id' => $book->user_id,
            'title' => '',
            'author' => '',
            'isbn' => '123',
            'published_date' => 'not-date',
            'genre_ids' => [],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'author', 'isbn', 'published_date', 'genre_ids']);

        $this->putJson('/api/v1/books/999', $this->validBookPayload(User::factory()->create()))
            ->assertNotFound()
            ->assertJsonPath('message', '書籍が見つかりません。');
    }

    public function test_book_delete_removes_book_and_related_data(): void
    {
        $owner = User::factory()->create();
        $reviewer = User::factory()->create();
        $genre = Genre::create(['name' => '文学']);
        $book = $this->createBook($owner, [], [$genre->id]);
        $review = $this->createReview($book, [], $reviewer);
        Favorite::create([
            'user_id' => $reviewer->id,
            'book_id' => $book->id,
            'created_at' => now(),
        ]);
        ReviewLike::create([
            'user_id' => $owner->id,
            'review_id' => $review->id,
            'created_at' => now(),
        ]);

        Sanctum::actingAs($owner);

        $this->deleteJson('/api/v1/books/'.$book->id)
            ->assertNoContent();

        $this->assertDatabaseMissing('books', ['id' => $book->id]);
        $this->assertDatabaseMissing('book_genre', ['book_id' => $book->id]);
        $this->assertDatabaseMissing('favorites', ['book_id' => $book->id]);
        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
        $this->assertDatabaseMissing('review_likes', ['review_id' => $review->id]);
    }

    public function test_book_delete_returns_json_error_for_missing_book(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson('/api/v1/books/999')
            ->assertNotFound()
            ->assertJsonPath('message', '書籍が見つかりません。');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validBookPayload(User $user, array $overrides = []): array
    {
        return array_merge([
            'user_id' => $user->id,
            'title' => 'APIテスト書籍',
            'author' => 'APIテスト著者',
            'isbn' => '9784000000100',
            'published_date' => '2020-01-01',
            'description' => 'API用の説明文です。',
            'image_url' => 'https://example.com/api-book.jpg',
            'genre_ids' => [],
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @param  array<int, int>  $genreIds
     */
    private function createBook(?User $user = null, array $overrides = [], array $genreIds = []): Book
    {
        $user ??= User::factory()->create();
        if ($genreIds === []) {
            $genreIds = [Genre::firstOrCreate(['name' => '文学'])->id];
        }

        $book = Book::create(array_merge([
            'user_id' => $user->id,
            'title' => 'テスト書籍',
            'author' => 'テスト著者',
            'isbn' => '9784000000100',
            'published_date' => '2020-01-01',
            'description' => '説明文です。',
            'image_url' => null,
        ], $overrides));

        $book->genres()->sync($genreIds);

        return $book;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createReview(Book $book, array $overrides = [], ?User $user = null): Review
    {
        $user ??= User::factory()->create();

        return Review::create(array_merge([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'rating' => 4,
            'comment' => 'レビュー本文です。',
        ], $overrides));
    }
}
