<?php

namespace Tests\Feature;

use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IsbnSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_search_and_see_google_books_data_in_registration_form(): void
    {
        $this->createGenre();
        Http::fake([
            'https://www.googleapis.com/books/v1/volumes*' => Http::response([
                'totalItems' => 1,
                'items' => [[
                    'volumeInfo' => [
                        'title' => '検索された本',
                        'authors' => ['著者A', '著者B'],
                        'publishedDate' => '2020-04-01',
                        'description' => '本の説明',
                        'imageLinks' => ['thumbnail' => 'https://example.test/cover.jpg'],
                        'categories' => ['文学'],
                    ],
                ]],
            ]),
        ]);

        $response = $this->actingAs(User::factory()->create())
            ->get(route('books.isbn-search', ['isbn' => '978-0 306-40615-7']));

        $response->assertOk()
            ->assertSee('検索された本')
            ->assertSee('著者A, 著者B')
            ->assertSee('2020-04-01')
            ->assertSee('本の説明')
            ->assertSee('https://example.test/cover.jpg')
            ->assertSee('9780306406157');
        Http::assertSent(fn ($request): bool => $request->url() === 'https://www.googleapis.com/books/v1/volumes?q=isbn%3A9780306406157');
    }

    public function test_invalid_isbn_does_not_call_external_api(): void
    {
        Http::fake();

        $response = $this->actingAs(User::factory()->create())
            ->get(route('books.isbn-search', ['isbn' => '9780306406158']));

        $response->assertRedirect()
            ->assertSessionHasErrors(['isbn' => 'ISBNは正しいISBN-10またはISBN-13で入力してください。']);
        Http::assertNothingSent();
    }

    public function test_guests_are_redirected_to_login_for_isbn_search(): void
    {
        $this->get(route('books.isbn-search', ['isbn' => '9780306406157']))
            ->assertRedirect('/login');
    }

    public function test_it_handles_no_results(): void
    {
        Http::fake(['*' => Http::response(['totalItems' => 0, 'items' => []])]);

        $this->actingAs(User::factory()->create())
            ->get(route('books.isbn-search', ['isbn' => '9780306406157']))
            ->assertOk()
            ->assertSee('ISBNに該当する書籍情報が見つかりません。')
            ->assertSee('9780306406157');
    }

    public function test_it_handles_abnormal_responses(): void
    {
        Http::fake(['*' => Http::response(['unexpected' => true])]);

        $this->actingAs(User::factory()->create())
            ->get(route('books.isbn-search', ['isbn' => '9780306406157']))
            ->assertOk()
            ->assertSee('書籍情報を取得できませんでした。時間をおいて再度お試しください。');
    }

    public function test_it_handles_external_api_error_status(): void
    {
        Http::fake(['*' => Http::response(['error' => 'server error'], 500)]);

        $this->actingAs(User::factory()->create())
            ->get(route('books.isbn-search', ['isbn' => '9780306406157']))
            ->assertOk()
            ->assertSee('書籍情報を取得できませんでした。時間をおいて再度お試しください。');
    }

    /** @dataProvider unavailableResponseProvider */
    public function test_it_handles_external_api_connection_failures(callable $failure): void
    {
        Http::fake(['*' => $failure]);

        $this->actingAs(User::factory()->create())
            ->get(route('books.isbn-search', ['isbn' => '9780306406157']))
            ->assertOk()
            ->assertSee('書籍情報サービスに接続できませんでした。時間をおいて再度お試しください。');
    }

    public static function unavailableResponseProvider(): array
    {
        return [
            'connection failure' => [static fn (): never => throw new ConnectionException('connection failed')],
            'timeout' => [static fn (): never => throw new ConnectionException('timeout')],
        ];
    }

    public function test_missing_optional_google_books_fields_are_empty(): void
    {
        Http::fake(['*' => Http::response(['items' => [['volumeInfo' => ['title' => 'タイトル']]]])]);

        $this->actingAs(User::factory()->create())
            ->get(route('books.isbn-search', ['isbn' => '9780306406157']))
            ->assertOk()
            ->assertSee('タイトル')
            ->assertSee('name="author"', false)
            ->assertSee('name="description"', false);
    }

    private function createGenre(): Genre
    {
        return Genre::create(['name' => '文学']);
    }
}
