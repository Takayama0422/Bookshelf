<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TokenAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_credentials_issue_bearer_token(): void
    {
        $user = User::factory()->create([
            'email' => 'api@example.com',
            'password' => 'secret-password',
        ]);

        $this->postJson('/api/tokens', [
            'email' => 'api@example.com',
            'password' => 'secret-password',
            'token_name' => 'テストトークン',
        ])
            ->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonStructure(['token_type', 'token']);

        $this->assertSame(1, $user->tokens()->count());
        $this->assertSame('テストトークン', $user->tokens()->firstOrFail()->name);
    }

    public function test_token_response_can_be_used_as_bearer_token(): void
    {
        User::factory()->create([
            'email' => 'api@example.com',
            'password' => 'secret-password',
        ]);
        $genre = Genre::create(['name' => '文学']);

        $token = $this->postJson('/api/tokens', [
            'email' => 'api@example.com',
            'password' => 'secret-password',
            'token_name' => 'API Client',
        ])->json('token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/books', $this->validBookPayload(['genre_ids' => [$genre->id]]))
            ->assertCreated()
            ->assertJsonPath('data.user_id', User::where('email', 'api@example.com')->value('id'));
    }

    public function test_wrong_email_cannot_issue_token(): void
    {
        User::factory()->create([
            'email' => 'api@example.com',
            'password' => 'secret-password',
        ]);

        $this->postJson('/api/tokens', [
            'email' => 'missing@example.com',
            'password' => 'secret-password',
            'token_name' => 'API Client',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('message', '認証情報が正しくありません。')
            ->assertJsonMissing(['password' => 'secret-password']);
    }

    public function test_wrong_password_cannot_issue_token(): void
    {
        User::factory()->create([
            'email' => 'api@example.com',
            'password' => 'secret-password',
        ]);

        $this->postJson('/api/tokens', [
            'email' => 'api@example.com',
            'password' => 'wrong-password',
            'token_name' => 'API Client',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('message', '認証情報が正しくありません。')
            ->assertJsonMissing(['password' => 'wrong-password']);
    }

    public function test_token_name_validation_works(): void
    {
        $this->postJson('/api/tokens', [
            'email' => 'api@example.com',
            'password' => 'secret-password',
            'token_name' => '',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['token_name'])
            ->assertJsonPath('errors.token_name.0', 'トークン名は必ず入力してください。');
    }

    public function test_guest_cannot_write_books(): void
    {
        $book = $this->createBook();

        $this->postJson('/api/v1/books', $this->validBookPayload())
            ->assertUnauthorized();

        $this->putJson('/api/v1/books/'.$book->id, $this->validBookPayload())
            ->assertUnauthorized();

        $this->deleteJson('/api/v1/books/'.$book->id)
            ->assertUnauthorized();
    }

    public function test_authenticated_user_can_create_book_and_request_user_id_cannot_be_tampered(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $genre = Genre::create(['name' => '文学']);

        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/books', $this->validBookPayload([
            'user_id' => $otherUser->id,
            'genre_ids' => [$genre->id],
        ]))
            ->assertCreated()
            ->assertJsonPath('data.user_id', $owner->id);

        $this->assertDatabaseHas('books', [
            'title' => 'APIテスト書籍',
            'user_id' => $owner->id,
        ]);
        $this->assertDatabaseMissing('books', [
            'title' => 'APIテスト書籍',
            'user_id' => $otherUser->id,
        ]);
    }

    public function test_owner_can_update_and_delete_book(): void
    {
        $owner = User::factory()->create();
        $genre = Genre::create(['name' => '文学']);
        $book = $this->createBook($owner, ['isbn' => '9784000000101'], [$genre->id]);

        Sanctum::actingAs($owner);

        $this->putJson('/api/v1/books/'.$book->id, $this->validBookPayload([
            'title' => '所有者更新',
            'isbn' => '9784000000101',
            'genre_ids' => [$genre->id],
        ]))
            ->assertOk()
            ->assertJsonPath('data.title', '所有者更新');

        $this->deleteJson('/api/v1/books/'.$book->id)
            ->assertNoContent();

        $this->assertDatabaseMissing('books', ['id' => $book->id]);
    }

    public function test_non_owner_cannot_update_or_delete_book(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $book = $this->createBook($owner);
        $genre = $book->genres()->firstOrFail();

        Sanctum::actingAs($otherUser);

        $this->putJson('/api/v1/books/'.$book->id, $this->validBookPayload([
            'isbn' => $book->isbn,
            'genre_ids' => [$genre->id],
        ]))
            ->assertForbidden();

        $this->deleteJson('/api/v1/books/'.$book->id)
            ->assertForbidden();
    }

    public function test_public_get_book_apis_remain_available_without_authentication(): void
    {
        $book = $this->createBook();

        $this->getJson('/api/v1/books')
            ->assertOk();

        $this->getJson('/api/v1/books/'.$book->id)
            ->assertOk()
            ->assertJsonPath('data.id', $book->id);
    }

    public function test_authenticated_missing_and_validation_responses_are_preserved(): void
    {
        $user = User::factory()->create();
        $book = $this->createBook($user);

        Sanctum::actingAs($user);

        $this->putJson('/api/v1/books/999', $this->validBookPayload())
            ->assertNotFound()
            ->assertJson(['error' => '書籍が見つかりませんでした。']);

        $this->putJson('/api/v1/books/'.$book->id, [
            'title' => '',
            'author' => '',
            'isbn' => '123',
            'published_date' => 'not-date',
            'genre_ids' => [],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'author', 'isbn', 'published_date', 'genres']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validBookPayload(array $overrides = []): array
    {
        return array_merge([
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
}
