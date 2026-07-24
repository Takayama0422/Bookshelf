<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Favorite;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FavoriteManagementTest extends TestCase
{
    use RefreshDatabase;

    private static int $isbnSequence = 1;

    public function test_authenticated_users_can_add_books_to_favorites(): void
    {
        $user = User::factory()->create();
        $book = $this->createBook();

        $this->actingAs($user)
            ->post(route('favorites.toggle', $book))
            ->assertRedirect(route('books.show', $book));

        $this->assertDatabaseHas('favorites', [
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);
    }

    public function test_registered_favorites_can_be_removed(): void
    {
        $user = User::factory()->create();
        $book = $this->createBook();
        $this->createFavorite($user, $book);

        $this->actingAs($user)
            ->post(route('favorites.toggle', $book))
            ->assertRedirect(route('books.show', $book));

        $this->assertDatabaseMissing('favorites', [
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);
    }

    public function test_favorite_toggle_adds_when_missing_and_removes_when_registered(): void
    {
        $user = User::factory()->create();
        $book = $this->createBook();

        $this->actingAs($user)->post(route('favorites.toggle', $book));

        $this->assertDatabaseHas('favorites', [
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);

        $this->actingAs($user)->post(route('favorites.toggle', $book));

        $this->assertDatabaseMissing('favorites', [
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);
    }

    public function test_duplicate_favorites_for_the_same_user_and_book_are_prevented(): void
    {
        $user = User::factory()->create();
        $book = $this->createBook();
        $this->createFavorite($user, $book);

        $this->actingAs($user)
            ->post(route('favorites.toggle', $book))
            ->assertRedirect(route('books.show', $book));

        $this->assertSame(0, Favorite::where('user_id', $user->id)->where('book_id', $book->id)->count());

        $this->actingAs($user)->post(route('favorites.toggle', $book));
        $this->actingAs($user)->post(route('favorites.toggle', $book));
        $this->actingAs($user)->post(route('favorites.toggle', $book));

        $this->assertSame(1, Favorite::where('user_id', $user->id)->where('book_id', $book->id)->count());
    }

    public function test_guests_are_redirected_to_login_when_toggling_favorites(): void
    {
        $book = $this->createBook();

        $this->post(route('favorites.toggle', $book))
            ->assertRedirect('/login');
    }

    public function test_authenticated_users_can_view_favorite_index(): void
    {
        $user = User::factory()->create();
        $book = $this->createBook(null, ['title' => 'お気に入り書籍']);
        $this->createFavorite($user, $book);

        $this->actingAs($user)
            ->get(route('favorites.index'))
            ->assertOk()
            ->assertSee('お気に入り一覧')
            ->assertSee('お気に入り書籍');
    }

    public function test_guests_are_redirected_to_login_when_viewing_favorite_index(): void
    {
        $this->get(route('favorites.index'))
            ->assertRedirect('/login');
    }

    public function test_only_the_authenticated_users_favorites_are_displayed(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $ownBook = $this->createBook(null, ['title' => '自分のお気に入り']);
        $otherBook = $this->createBook(null, ['title' => '他人のお気に入り']);
        $this->createFavorite($user, $ownBook);
        $this->createFavorite($otherUser, $otherBook);

        $this->actingAs($user)
            ->get(route('favorites.index'))
            ->assertOk()
            ->assertSee('自分のお気に入り')
            ->assertDontSee('他人のお気に入り');
    }

    public function test_favorite_index_is_paginated_by_ten_books(): void
    {
        $user = User::factory()->create();

        for ($i = 1; $i <= 11; $i++) {
            $book = $this->createBook(null, [
                'title' => sprintf('お気に入り書籍%02d', $i),
                'isbn' => sprintf('9784000001%03d', $i),
            ]);
            $this->createFavorite($user, $book, now()->addMinutes($i));
        }

        $this->actingAs($user)
            ->get(route('favorites.index'))
            ->assertOk()
            ->assertSee('お気に入り書籍11')
            ->assertSee('お気に入り書籍02')
            ->assertDontSee('お気に入り書籍01');

        $this->actingAs($user)
            ->get(route('favorites.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('お気に入り書籍01')
            ->assertDontSee('お気に入り書籍11');
    }

    public function test_users_can_navigate_from_favorite_index_to_book_detail(): void
    {
        $user = User::factory()->create();
        $book = $this->createBook(null, ['title' => '詳細へ遷移する書籍']);
        $this->createFavorite($user, $book);

        $this->actingAs($user)
            ->get(route('favorites.index'))
            ->assertOk()
            ->assertSee(route('books.show', $book), false);
    }

    public function test_favorite_toggle_returns_404_for_missing_books(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/books/999999/favorites')
            ->assertNotFound();
    }

    public function test_favorite_toggle_uses_the_specified_uri_and_redirects_to_book_detail(): void
    {
        $user = User::factory()->create();
        $book = $this->createBook();

        $this->assertSame("/books/{$book->id}/favorites", route('favorites.toggle', $book, false));

        $this->actingAs($user)
            ->post("/books/{$book->id}/favorites")
            ->assertRedirect(route('books.show', $book));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createBook(?User $user = null, array $overrides = []): Book
    {
        $user ??= User::factory()->create();
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

    private function createFavorite(User $user, Book $book, mixed $createdAt = null): Favorite
    {
        return Favorite::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'created_at' => $createdAt ?? now(),
        ]);
    }
}
