<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\ReviewLike;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewManagementTest extends TestCase
{
    use RefreshDatabase;

    private static int $isbnSequence = 1;

    public function test_authenticated_users_can_store_reviews(): void
    {
        $user = User::factory()->create();
        $book = $this->createBook();

        $response = $this->actingAs($user)
            ->post(route('reviews.store', $book), $this->validReviewPayload());

        $response->assertRedirect(route('books.show', $book))
            ->assertSessionHas('success', 'レビューを投稿しました。');

        $this->assertDatabaseHas('reviews', [
            'user_id' => $user->id,
            'book_id' => $book->id,
            'rating' => 5,
            'comment' => 'とても参考になりました。',
        ]);
    }

    public function test_guests_are_redirected_to_login_when_storing_reviews(): void
    {
        $book = $this->createBook();

        $this->post(route('reviews.store', $book), $this->validReviewPayload())
            ->assertRedirect('/login');
    }

    public function test_review_store_returns_404_for_missing_books(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/books/999999/reviews', $this->validReviewPayload())
            ->assertNotFound();
    }

    public function test_review_validation_rejects_rating_outside_one_to_five(): void
    {
        $user = User::factory()->create();
        $book = $this->createBook();

        $this->actingAs($user)
            ->from(route('books.show', $book))
            ->post(route('reviews.store', $book), $this->validReviewPayload(['rating' => 6]))
            ->assertRedirect(route('books.show', $book))
            ->assertSessionHasErrors('rating')
            ->assertSessionHasInput('comment', 'とても参考になりました。');
    }

    public function test_review_validation_rejects_invalid_comments(): void
    {
        $user = User::factory()->create();
        $book = $this->createBook();

        $this->actingAs($user)
            ->from(route('books.show', $book))
            ->post(route('reviews.store', $book), $this->validReviewPayload(['comment' => '']))
            ->assertRedirect(route('books.show', $book))
            ->assertSessionHasErrors('comment');

        $this->actingAs($user)
            ->from(route('books.show', $book))
            ->post(route('reviews.store', $book), $this->validReviewPayload(['comment' => str_repeat('あ', 1001)]))
            ->assertRedirect(route('books.show', $book))
            ->assertSessionHasErrors('comment');
    }

    public function test_users_cannot_store_duplicate_reviews_for_the_same_book(): void
    {
        $user = User::factory()->create();
        $book = $this->createBook();
        $this->createReview($user, $book);

        $this->actingAs($user)
            ->from(route('books.show', $book))
            ->post(route('reviews.store', $book), $this->validReviewPayload(['comment' => '二回目の投稿です。']))
            ->assertRedirect(route('books.show', $book))
            ->assertSessionHasErrors('comment');

        $this->assertSame(1, Review::where('user_id', $user->id)->where('book_id', $book->id)->count());
    }

    public function test_owners_can_view_review_edit_screen(): void
    {
        $user = User::factory()->create();
        $review = $this->createReview($user);

        $this->actingAs($user)
            ->get(route('reviews.edit', $review))
            ->assertOk()
            ->assertSee('レビューの編集')
            ->assertSee($review->comment);
    }

    public function test_owners_can_update_reviews(): void
    {
        $user = User::factory()->create();
        $review = $this->createReview($user);

        $this->actingAs($user)
            ->put(route('reviews.update', $review), $this->validReviewPayload([
                'rating' => 3,
                'comment' => '更新後のコメントです。',
            ]))
            ->assertRedirect(route('books.show', $review->book))
            ->assertSessionHas('success', 'レビューを更新しました。');

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'rating' => 3,
            'comment' => '更新後のコメントです。',
        ]);
    }

    public function test_owners_can_delete_reviews(): void
    {
        $user = User::factory()->create();
        $review = $this->createReview($user);

        $this->actingAs($user)
            ->delete(route('reviews.destroy', $review))
            ->assertRedirect(route('books.show', $review->book))
            ->assertSessionHas('success', 'レビューを削除しました。');

        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
    }

    public function test_other_users_cannot_access_review_edit_screen(): void
    {
        $review = $this->createReview();

        $this->actingAs(User::factory()->create())
            ->get(route('reviews.edit', $review))
            ->assertForbidden();
    }

    public function test_other_users_cannot_update_reviews(): void
    {
        $owner = User::factory()->create();
        $review = $this->createReview($owner, null, ['comment' => '元のコメントです。']);

        $this->actingAs(User::factory()->create())
            ->put(route('reviews.update', $review), $this->validReviewPayload(['comment' => '不正な更新です。']))
            ->assertForbidden();

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'comment' => '元のコメントです。',
        ]);
    }

    public function test_other_users_cannot_delete_reviews(): void
    {
        $review = $this->createReview();

        $this->actingAs(User::factory()->create())
            ->delete(route('reviews.destroy', $review))
            ->assertForbidden();

        $this->assertDatabaseHas('reviews', ['id' => $review->id]);
    }

    public function test_missing_reviews_return_404(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/reviews/999999/edit')->assertNotFound();
        $this->actingAs($user)->put('/reviews/999999', $this->validReviewPayload())->assertNotFound();
        $this->actingAs($user)->delete('/reviews/999999')->assertNotFound();
    }

    public function test_deleting_reviews_keeps_related_likes_consistent(): void
    {
        $owner = User::factory()->create();
        $liker = User::factory()->create();
        $review = $this->createReview($owner);
        ReviewLike::create([
            'user_id' => $liker->id,
            'review_id' => $review->id,
            'created_at' => now(),
        ]);

        $this->actingAs($owner)->delete(route('reviews.destroy', $review));

        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
        $this->assertDatabaseMissing('review_likes', ['review_id' => $review->id]);
    }

    public function test_authenticated_users_can_like_reviews(): void
    {
        $user = User::factory()->create();
        $review = $this->createReview();

        $this->actingAs($user)
            ->post(route('reviews.like', $review))
            ->assertRedirect(route('books.show', $review->book));

        $this->assertDatabaseHas('review_likes', [
            'user_id' => $user->id,
            'review_id' => $review->id,
        ]);
    }

    public function test_registered_review_likes_can_be_removed(): void
    {
        $user = User::factory()->create();
        $review = $this->createReview();
        $this->createReviewLike($user, $review);

        $this->actingAs($user)
            ->post(route('reviews.like', $review))
            ->assertRedirect(route('books.show', $review->book));

        $this->assertDatabaseMissing('review_likes', [
            'user_id' => $user->id,
            'review_id' => $review->id,
        ]);
    }

    public function test_review_like_toggle_adds_when_missing_and_removes_when_registered(): void
    {
        $user = User::factory()->create();
        $review = $this->createReview();

        $this->actingAs($user)->post(route('reviews.like', $review));

        $this->assertDatabaseHas('review_likes', [
            'user_id' => $user->id,
            'review_id' => $review->id,
        ]);

        $this->actingAs($user)->post(route('reviews.like', $review));

        $this->assertDatabaseMissing('review_likes', [
            'user_id' => $user->id,
            'review_id' => $review->id,
        ]);
    }

    public function test_duplicate_review_likes_for_the_same_user_and_review_are_prevented(): void
    {
        $user = User::factory()->create();
        $review = $this->createReview();
        $this->createReviewLike($user, $review);

        $this->actingAs($user)
            ->post(route('reviews.like', $review))
            ->assertRedirect(route('books.show', $review->book));

        $this->assertSame(0, ReviewLike::where('user_id', $user->id)->where('review_id', $review->id)->count());

        $this->actingAs($user)->post(route('reviews.like', $review));
        $this->actingAs($user)->post(route('reviews.like', $review));
        $this->actingAs($user)->post(route('reviews.like', $review));

        $this->assertSame(1, ReviewLike::where('user_id', $user->id)->where('review_id', $review->id)->count());
    }

    public function test_guests_are_redirected_to_login_when_liking_reviews(): void
    {
        $review = $this->createReview();

        $this->post(route('reviews.like', $review))
            ->assertRedirect('/login');
    }

    public function test_review_like_returns_404_for_missing_reviews(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/reviews/999999/like')
            ->assertNotFound();
    }

    public function test_review_like_uses_the_specified_uri_and_redirects_to_book_detail(): void
    {
        $user = User::factory()->create();
        $review = $this->createReview();

        $this->assertSame("/reviews/{$review->id}/like", route('reviews.like', $review, false));

        $this->actingAs($user)
            ->post("/reviews/{$review->id}/like")
            ->assertRedirect(route('books.show', $review->book));
    }

    public function test_book_detail_displays_review_like_state_and_count(): void
    {
        $user = User::factory()->create();
        $review = $this->createReview();
        $this->createReviewLike($user, $review);
        $this->createReviewLike(User::factory()->create(), $review);

        $this->actingAs($user)
            ->get(route('books.show', $review->book))
            ->assertOk()
            ->assertSee('いいね済み (2)');
    }

    public function test_review_update_validation_keeps_old_input(): void
    {
        $user = User::factory()->create();
        $review = $this->createReview($user);

        $this->actingAs($user)
            ->from(route('reviews.edit', $review))
            ->put(route('reviews.update', $review), $this->validReviewPayload([
                'rating' => 0,
                'comment' => '保持されるコメントです。',
            ]))
            ->assertRedirect(route('reviews.edit', $review))
            ->assertSessionHasErrors('rating')
            ->assertSessionHasInput('comment', '保持されるコメントです。');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validReviewPayload(array $overrides = []): array
    {
        return array_merge([
            'rating' => 5,
            'comment' => 'とても参考になりました。',
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
            'isbn' => '9784'.str_pad((string) self::$isbnSequence++, 9, '0', STR_PAD_LEFT),
            'published_date' => '2020-01-01',
            'description' => '説明文です。',
            'image_url' => null,
        ], $overrides));

        $book->genres()->sync([$genre->id]);

        return $book;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createReview(?User $user = null, ?Book $book = null, array $overrides = []): Review
    {
        $user ??= User::factory()->create();
        $book ??= $this->createBook();

        return Review::create(array_merge([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'rating' => 4,
            'comment' => '元のレビューです。',
        ], $overrides));
    }

    private function createReviewLike(User $user, Review $review, mixed $createdAt = null): ReviewLike
    {
        return ReviewLike::create([
            'user_id' => $user->id,
            'review_id' => $review->id,
            'created_at' => $createdAt ?? now(),
        ]);
    }
}
