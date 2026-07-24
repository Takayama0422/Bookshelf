<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Favorite;
use App\Models\Genre;
use App\Models\Review;
use App\Models\ReviewLike;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_view_book_index(): void
    {
        $book = $this->createBook();

        $this->get('/books')
            ->assertOk()
            ->assertSee($book->title);
    }

    public function test_guests_can_view_book_detail(): void
    {
        $book = $this->createBook();

        $this->get(route('books.show', $book))
            ->assertOk()
            ->assertSee($book->title)
            ->assertSee($book->author);
    }

    public function test_book_index_is_paginated_by_ten_books(): void
    {
        $genre = Genre::create(['name' => '文学']);
        $user = User::factory()->create();

        for ($i = 1; $i <= 11; $i++) {
            $book = $this->createBook($user, [
                'title' => sprintf('書籍%02d', $i),
                'isbn' => sprintf('9784000000%03d', $i),
            ], [$genre->id]);
            $book->forceFill(['created_at' => now()->addMinutes($i)])->save();
        }

        $this->get('/books')
            ->assertOk()
            ->assertSee('書籍11')
            ->assertSee('書籍02')
            ->assertDontSee('書籍01');

        $this->get('/books?page=2')
            ->assertOk()
            ->assertSee('書籍01')
            ->assertDontSee('書籍11');
    }

    public function test_authenticated_users_can_view_book_create_screen(): void
    {
        $user = User::factory()->create();
        $genre = Genre::create(['name' => '文学']);

        $this->actingAs($user)
            ->get('/books/create')
            ->assertOk()
            ->assertSee('書籍の登録')
            ->assertSee($genre->name);
    }

    public function test_guests_are_redirected_to_login_when_accessing_book_create_screen(): void
    {
        $this->get('/books/create')
            ->assertRedirect('/login');
    }

    public function test_authenticated_users_can_store_books_with_valid_input(): void
    {
        $user = User::factory()->create();
        $genre = Genre::create(['name' => '文学']);

        $response = $this->actingAs($user)
            ->post('/books', $this->validBookPayload(['genres' => [$genre->id]]));

        $book = Book::firstOrFail();
        $response->assertRedirect(route('books.show', $book))
            ->assertSessionHas('success', '書籍を登録しました。');

        $this->assertDatabaseHas('books', [
            'user_id' => $user->id,
            'title' => 'テスト書籍',
            'isbn' => '9784000000001',
        ]);
        $this->assertDatabaseHas('book_genre', [
            'book_id' => $book->id,
            'genre_id' => $genre->id,
        ]);
    }

    public function test_authenticated_users_can_store_books_with_multiple_genres(): void
    {
        $user = User::factory()->create();
        $genres = [
            Genre::create(['name' => '文学']),
            Genre::create(['name' => '技術書']),
        ];

        $this->actingAs($user)
            ->post('/books', $this->validBookPayload([
                'genres' => [$genres[0]->id, $genres[1]->id],
            ]))
            ->assertSessionHasNoErrors();

        $book = Book::firstOrFail();
        $this->assertSame(
            [$genres[0]->id, $genres[1]->id],
            $book->genres()->orderBy('genres.id')->pluck('genres.id')->all()
        );
    }

    public function test_book_validation_rejects_required_format_digits_url_and_future_date_errors(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->from('/books/create')
            ->post('/books', [
                'title' => '',
                'author' => '',
                'isbn' => '123456789012',
                'published_date' => now()->addDay()->toDateString(),
                'description' => str_repeat('あ', 2001),
                'image_url' => 'not-url',
                'genres' => [],
            ]);

        $response->assertRedirect('/books/create')
            ->assertSessionHasErrors([
                'title',
                'author',
                'isbn',
                'published_date',
                'description',
                'image_url',
                'genres',
            ]);

        $this->followRedirects($response)
            ->assertSee('タイトルは必ず入力してください。')
            ->assertSee('著者は必ず入力してください。')
            ->assertSee('ISBNは13桁で入力してください。')
            ->assertSee('出版日には本日以前の日付を指定してください。')
            ->assertSee('説明は2000文字以内で入力してください。')
            ->assertSee('ジャンルは1つ以上選択してください。')
            ->assertSee('画像URLには有効なURLを指定してください。');
    }

    public function test_book_store_rejects_duplicate_isbn(): void
    {
        $user = User::factory()->create();
        $genre = Genre::create(['name' => '文学']);
        $this->createBook(null, ['isbn' => '9784000000001'], [$genre->id]);

        $response = $this->actingAs($user)
            ->from('/books/create')
            ->post('/books', $this->validBookPayload(['genres' => [$genre->id]]))
            ->assertRedirect('/books/create')
            ->assertSessionHasErrors('isbn');

        $this->followRedirects($response)
            ->assertSee('このISBNはすでに登録されています。');
    }

    public function test_owners_can_view_book_edit_screen(): void
    {
        $user = User::factory()->create();
        $genre = Genre::create(['name' => '文学']);
        $book = $this->createBook($user, [], [$genre->id]);

        $this->actingAs($user)
            ->get(route('books.edit', $book))
            ->assertOk()
            ->assertSee('書籍の編集')
            ->assertSee($book->title);
    }

    public function test_book_update_allows_own_isbn(): void
    {
        $user = User::factory()->create();
        $genre = Genre::create(['name' => '文学']);
        $book = $this->createBook($user, ['isbn' => '9784000000001'], [$genre->id]);

        $this->actingAs($user)
            ->put(route('books.update', $book), $this->validBookPayload([
                'title' => '更新後タイトル',
                'isbn' => '9784000000001',
                'genres' => [$genre->id],
            ]))
            ->assertRedirect(route('books.show', $book))
            ->assertSessionHas('success', '書籍を更新しました。');

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'title' => '更新後タイトル',
            'isbn' => '9784000000001',
        ]);
    }

    public function test_other_users_cannot_access_book_edit_screen(): void
    {
        $book = $this->createBook();

        $this->actingAs(User::factory()->create())
            ->get(route('books.edit', $book))
            ->assertForbidden();
    }

    public function test_other_users_cannot_update_books(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $genre = Genre::create(['name' => '文学']);
        $book = $this->createBook($owner, ['title' => '元のタイトル'], [$genre->id]);

        $this->actingAs($otherUser)
            ->put(route('books.update', $book), $this->validBookPayload([
                'title' => '不正な更新',
                'genres' => [$genre->id],
            ]))
            ->assertForbidden();

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'title' => '元のタイトル',
        ]);
    }

    public function test_owners_can_delete_books(): void
    {
        $user = User::factory()->create();
        $book = $this->createBook($user);

        $this->actingAs($user)
            ->delete(route('books.destroy', $book))
            ->assertRedirect(route('books.index'))
            ->assertSessionHas('success', '書籍を削除しました。');

        $this->assertDatabaseMissing('books', ['id' => $book->id]);
    }

    public function test_other_users_cannot_delete_books(): void
    {
        $book = $this->createBook();

        $this->actingAs(User::factory()->create())
            ->delete(route('books.destroy', $book))
            ->assertForbidden();

        $this->assertDatabaseHas('books', ['id' => $book->id]);
    }

    public function test_deleting_books_keeps_related_data_consistent(): void
    {
        $owner = User::factory()->create();
        $reviewer = User::factory()->create();
        $genre = Genre::create(['name' => '文学']);
        $book = $this->createBook($owner, [], [$genre->id]);
        $review = Review::create([
            'user_id' => $reviewer->id,
            'book_id' => $book->id,
            'rating' => 5,
            'comment' => '良い本でした。',
        ]);
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

        $this->actingAs($owner)->delete(route('books.destroy', $book));

        $this->assertDatabaseMissing('book_genre', ['book_id' => $book->id]);
        $this->assertDatabaseMissing('favorites', ['book_id' => $book->id]);
        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
        $this->assertDatabaseMissing('review_likes', ['review_id' => $review->id]);
    }

    public function test_validation_errors_keep_old_input(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/books/create')
            ->post('/books', $this->validBookPayload([
                'title' => '保持されるタイトル',
                'isbn' => 'invalid',
                'genres' => [],
            ]))
            ->assertRedirect('/books/create')
            ->assertSessionHasErrors(['isbn', 'genres'])
            ->assertSessionHasInput('title', '保持されるタイトル');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validBookPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'テスト書籍',
            'author' => 'テスト著者',
            'isbn' => '9784000000001',
            'published_date' => '2020-01-01',
            'description' => '説明文です。',
            'image_url' => 'https://example.com/book.jpg',
            'genres' => [],
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
            $genreIds = [Genre::create(['name' => '文学'])->id];
        }

        $book = Book::create(array_merge([
            'user_id' => $user->id,
            'title' => 'テスト書籍',
            'author' => 'テスト著者',
            'isbn' => '9784000000001',
            'published_date' => '2020-01-01',
            'description' => '説明文です。',
            'image_url' => null,
        ], $overrides));

        $book->genres()->sync($genreIds);

        return $book;
    }
}
