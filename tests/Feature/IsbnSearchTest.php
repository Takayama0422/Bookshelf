<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IsbnSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_search_by_isbn_and_receive_json(): void
    {
        Http::fake([
            'https://www.googleapis.com/books/v1/volumes*' => Http::response([
                'items' => [[
                    'volumeInfo' => [
                        'title' => '検索された本',
                        'authors' => ['著者A', '著者B'],
                        'publishedDate' => '2020-04-01',
                        'description' => '本の説明',
                        'imageLinks' => ['thumbnail' => 'https://example.test/cover.jpg'],
                    ],
                ]],
            ]),
        ]);

        $this->actingAs($this->createUser())
            ->getJson('/books/isbn/9780306406157')
            ->assertOk()
            ->assertJson([
                'title' => '検索された本',
                'author' => '著者A, 著者B',
                'published_date' => '2020-04-01',
                'description' => '本の説明',
                'image_url' => 'https://example.test/cover.jpg',
                'isbn' => '9780306406157',
            ]);
    }

    public function test_isbn_must_be_thirteen_digits(): void
    {
        Http::fake();

        $this->actingAs($this->createUser())
            ->getJson('/books/isbn/978030640615')
            ->assertStatus(400)
            ->assertJson(['error' => 'ISBNは13桁で入力してください。']);

        Http::assertNothingSent();
    }

    public function test_guests_are_redirected_to_login_for_isbn_search(): void
    {
        $this->get('/books/isbn/9780306406157')->assertRedirect('/login');
    }

    public function test_empty_results_return_not_found(): void
    {
        Http::fake(['*' => Http::response(['items' => []])]);

        $this->actingAs($this->createUser())
            ->getJson('/books/isbn/9780306406157')
            ->assertStatus(404)
            ->assertJson(['error' => '書籍が見つかりませんでした。']);
    }

    public function test_quota_exceeded_returns_too_many_requests(): void
    {
        Http::fake(['*' => Http::response([], 429)]);

        $this->actingAs($this->createUser())
            ->getJson('/books/isbn/9780306406157')
            ->assertStatus(429)
            ->assertJson(['error' => 'Google Books API のクォータを超過しました。.env に GOOGLE_BOOKS_API_KEY を設定してください。']);
    }

    public function test_api_failures_return_internal_server_error(): void
    {
        Http::fake(['*' => Http::response([], 500)]);

        $this->actingAs($this->createUser())
            ->getJson('/books/isbn/9780306406157')
            ->assertStatus(500)
            ->assertJson(['error' => 'API通信エラーが発生しました。']);

        Http::fake(['*' => static fn (): never => throw new ConnectionException('connection failed')]);

        $this->actingAs($this->createUser())
            ->getJson('/books/isbn/9780306406157')
            ->assertStatus(500)
            ->assertJson(['error' => 'API通信エラーが発生しました。']);
    }

    private function createUser(): User
    {
        return User::factory()->create();
    }
}
