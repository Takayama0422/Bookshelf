<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BasicFeatureRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_core_book_review_favorite_ranking_and_public_api_flow_works(): void
    {
        $genre = Genre::create(['name' => '技術書']);
        $owner = User::factory()->create();
        $reviewer = User::factory()->create();
        $liker = User::factory()->create();

        $storeResponse = $this->actingAs($owner)
            ->post(route('books.store'), $this->validBookPayload([
                'genres' => [$genre->id],
            ]));

        $book = Book::firstOrFail();
        $storeResponse->assertRedirect(route('books.show', $book));

        $this->get(route('books.index'))
            ->assertOk()
            ->assertSee('横断確認書籍');

        $this->get(route('books.show', $book))
            ->assertOk()
            ->assertSee('横断確認書籍')
            ->assertSee('技術書');

        $this->actingAs($reviewer)
            ->post(route('reviews.store', $book), [
                'rating' => 5,
                'comment' => '主要機能の回帰確認レビューです。',
            ])
            ->assertRedirect(route('books.show', $book));

        $review = Review::firstOrFail();

        $this->actingAs($liker)
            ->post(route('favorites.toggle', $book))
            ->assertRedirect(route('books.show', $book));

        $this->actingAs($liker)
            ->post(route('reviews.like', $review))
            ->assertRedirect(route('books.show', $book));

        $this->actingAs($liker)
            ->get(route('favorites.index'))
            ->assertOk()
            ->assertSee('横断確認書籍');

        $this->get(route('ranking.index'))
            ->assertOk()
            ->assertSee('横断確認書籍');

        $this->getJson('/api/v1/books?keyword=横断&genre='.$genre->id)
            ->assertOk()
            ->assertJsonPath('data.0.id', $book->id)
            ->assertJsonPath('data.0.average_rating', 5)
            ->assertJsonPath('data.0.review_count', 1);

        $this->getJson('/api/v1/books/'.$book->id)
            ->assertOk()
            ->assertJsonPath('data.id', $book->id)
            ->assertJsonPath('data.genres.0.name', '技術書')
            ->assertJsonPath('data.reviews.0.id', $review->id)
            ->assertJsonPath('data.reviews.0.comment', '主要機能の回帰確認レビューです。');

        $this->assertDatabaseHas('favorites', [
            'user_id' => $liker->id,
            'book_id' => $book->id,
        ]);
        $this->assertDatabaseHas('review_likes', [
            'user_id' => $liker->id,
            'review_id' => $review->id,
        ]);
    }

    public function test_guest_users_are_redirected_from_basic_protected_web_actions(): void
    {
        $book = $this->createBook();
        $review = Review::create([
            'user_id' => User::factory()->create()->id,
            'book_id' => $book->id,
            'rating' => 4,
            'comment' => 'ゲスト保護確認用レビューです。',
        ]);
        $genre = Genre::create(['name' => '未ログイン確認']);

        $this->get(route('books.create'))->assertRedirect('/login');
        $this->post(route('books.store'), $this->validBookPayload())->assertRedirect('/login');
        $this->get(route('books.edit', $book))->assertRedirect('/login');
        $this->put(route('books.update', $book), $this->validBookPayload())->assertRedirect('/login');
        $this->delete(route('books.destroy', $book))->assertRedirect('/login');
        $this->get(route('favorites.index'))->assertRedirect('/login');
        $this->post(route('favorites.toggle', $book))->assertRedirect('/login');
        $this->post(route('reviews.store', $book), ['rating' => 5, 'comment' => '未ログイン投稿です。'])->assertRedirect('/login');
        $this->get(route('reviews.edit', $review))->assertRedirect('/login');
        $this->put(route('reviews.update', $review), ['rating' => 3, 'comment' => '未ログイン更新です。'])->assertRedirect('/login');
        $this->delete(route('reviews.destroy', $review))->assertRedirect('/login');
        $this->post(route('reviews.like', $review))->assertRedirect('/login');
        $this->get(route('genres.index'))->assertRedirect('/login');
        $this->post(route('genres.store'), ['name' => 'ゲスト作成'])->assertRedirect('/login');
        $this->put(route('genres.update', $genre), ['name' => 'ゲスト更新'])->assertRedirect('/login');
        $this->delete(route('genres.destroy', $genre))->assertRedirect('/login');
    }

    public function test_users_cannot_modify_other_users_books_or_reviews_in_basic_flow(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $book = $this->createBook($owner, [
            'title' => '所有者の書籍',
            'isbn' => '9784000000201',
        ]);
        $review = Review::create([
            'user_id' => $owner->id,
            'book_id' => $book->id,
            'rating' => 4,
            'comment' => '所有者のレビューです。',
        ]);

        $this->actingAs($otherUser)
            ->put(route('books.update', $book), $this->validBookPayload([
                'title' => '他人による更新',
                'isbn' => '9784000000201',
                'genres' => [$book->genres()->firstOrFail()->id],
            ]))
            ->assertForbidden();

        $this->actingAs($otherUser)
            ->delete(route('books.destroy', $book))
            ->assertForbidden();

        $this->actingAs($otherUser)
            ->put(route('reviews.update', $review), [
                'rating' => 1,
                'comment' => '他人によるレビュー更新です。',
            ])
            ->assertForbidden();

        $this->actingAs($otherUser)
            ->delete(route('reviews.destroy', $review))
            ->assertForbidden();

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'title' => '所有者の書籍',
        ]);
        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'comment' => '所有者のレビューです。',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validBookPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => '横断確認書籍',
            'author' => '横断確認著者',
            'isbn' => '9784000000200',
            'published_date' => '2020-01-01',
            'description' => '基本機能を横断確認するための説明文です。',
            'image_url' => 'https://example.com/basic-regression.jpg',
            'genres' => [],
        ], $overrides);
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
            'isbn' => '9784000000200',
            'published_date' => '2020-01-01',
            'description' => '説明文です。',
            'image_url' => null,
        ], $overrides));

        $book->genres()->sync([$genre->id]);

        return $book;
    }
}
