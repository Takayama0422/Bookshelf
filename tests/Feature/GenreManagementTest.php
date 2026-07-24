<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenreManagementTest extends TestCase
{
    use RefreshDatabase;

    private static int $isbnSequence = 1;

    public function test_authenticated_users_can_view_genre_index(): void
    {
        $user = User::factory()->create();
        $genre = Genre::create(['name' => '文学']);
        $this->createBook(null, ['title' => 'ジャンル付き書籍'], [$genre->id]);

        $this->actingAs($user)
            ->get(route('genres.index'))
            ->assertOk()
            ->assertSee('ジャンル管理')
            ->assertSee('文学')
            ->assertSee('1冊');
    }

    public function test_guests_are_redirected_to_login_when_viewing_genre_index(): void
    {
        $this->get(route('genres.index'))
            ->assertRedirect('/login');
    }

    public function test_authenticated_users_can_view_genre_detail(): void
    {
        $user = User::factory()->create();
        $genre = Genre::create(['name' => '技術書']);
        $this->createBook(null, ['title' => 'Laravel実践'], [$genre->id]);

        $this->actingAs($user)
            ->get(route('genres.show', $genre))
            ->assertOk()
            ->assertSee('ジャンル: 技術書')
            ->assertSee('Laravel実践');
    }

    public function test_genre_detail_shows_ten_books_per_page(): void
    {
        $user = User::factory()->create();
        $genre = Genre::create(['name' => '技術書']);

        foreach (range(1, 11) as $index) {
            $this->createBook(null, [
                'title' => 'ジャンル本'.$index,
                'created_at' => now()->subMinutes($index),
            ], [$genre->id]);
        }

        $this->actingAs($user)
            ->get(route('genres.show', $genre))
            ->assertOk()
            ->assertSee('ジャンル本1')
            ->assertSee('ジャンル本10')
            ->assertDontSee('ジャンル本11');

        $this->actingAs($user)
            ->get(route('genres.show', ['genre' => $genre, 'page' => 2]))
            ->assertOk()
            ->assertSee('ジャンル本11');
    }

    public function test_authenticated_users_can_create_genres(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post(route('genres.store'), ['name' => '新ジャンル']);

        $genre = Genre::firstOrFail();
        $response->assertRedirect(route('genres.show', $genre))
            ->assertSessionHas('success', 'ジャンルを登録しました。');

        $this->assertDatabaseHas('genres', ['name' => '新ジャンル']);
    }

    public function test_genre_creation_rejects_duplicate_and_invalid_names(): void
    {
        $user = User::factory()->create();
        Genre::create(['name' => '文学']);

        $duplicateResponse = $this->actingAs($user)
            ->from(route('genres.create'))
            ->post(route('genres.store'), ['name' => '文学'])
            ->assertRedirect(route('genres.create'))
            ->assertSessionHasErrors('name');

        $this->followRedirects($duplicateResponse)
            ->assertSee('このジャンル名はすでに登録されています。');

        $tooLongResponse = $this->actingAs($user)
            ->from(route('genres.create'))
            ->post(route('genres.store'), ['name' => str_repeat('あ', 51)])
            ->assertRedirect(route('genres.create'))
            ->assertSessionHasErrors('name');

        $this->followRedirects($tooLongResponse)
            ->assertSee('ジャンル名は50文字以内で入力してください。');
    }

    public function test_authenticated_users_can_update_genres(): void
    {
        $user = User::factory()->create();
        $genre = Genre::create(['name' => '旧ジャンル']);

        $this->actingAs($user)
            ->put(route('genres.update', $genre), ['name' => '更新ジャンル'])
            ->assertRedirect(route('genres.show', $genre))
            ->assertSessionHas('success', 'ジャンルを更新しました。');

        $this->assertDatabaseHas('genres', [
            'id' => $genre->id,
            'name' => '更新ジャンル',
        ]);
    }

    public function test_genre_update_allows_own_name_and_rejects_duplicate_names(): void
    {
        $user = User::factory()->create();
        $genre = Genre::create(['name' => '文学']);
        Genre::create(['name' => '技術書']);

        $this->actingAs($user)
            ->put(route('genres.update', $genre), ['name' => '文学'])
            ->assertRedirect(route('genres.show', $genre))
            ->assertSessionHasNoErrors();

        $response = $this->actingAs($user)
            ->from(route('genres.edit', $genre))
            ->put(route('genres.update', $genre), ['name' => '技術書'])
            ->assertRedirect(route('genres.edit', $genre))
            ->assertSessionHasErrors('name');

        $this->followRedirects($response)
            ->assertSee('このジャンル名はすでに登録されています。');
    }

    public function test_unused_genres_can_be_deleted(): void
    {
        $user = User::factory()->create();
        $genre = Genre::create(['name' => '未使用ジャンル']);

        $this->actingAs($user)
            ->delete(route('genres.destroy', $genre))
            ->assertRedirect(route('genres.index'))
            ->assertSessionHas('success', 'ジャンルを削除しました。');

        $this->assertDatabaseMissing('genres', ['id' => $genre->id]);
    }

    public function test_genres_used_by_books_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $genre = Genre::create(['name' => '使用中ジャンル']);
        $this->createBook(null, [], [$genre->id]);

        $this->actingAs($user)
            ->delete(route('genres.destroy', $genre))
            ->assertRedirect(route('genres.index'))
            ->assertSessionHas('error', '書籍に利用されているジャンルは削除できません。');

        $this->assertDatabaseHas('genres', ['id' => $genre->id]);
    }

    public function test_guests_cannot_create_update_or_delete_genres(): void
    {
        $genre = Genre::create(['name' => '文学']);

        $this->post(route('genres.store'), ['name' => '新規'])
            ->assertRedirect('/login');
        $this->put(route('genres.update', $genre), ['name' => '更新'])
            ->assertRedirect('/login');
        $this->delete(route('genres.destroy', $genre))
            ->assertRedirect('/login');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @param  array<int, int>  $genreIds
     */
    private function createBook(?User $user = null, array $overrides = [], array $genreIds = []): Book
    {
        $user ??= User::factory()->create();
        if ($genreIds === []) {
            $genreIds = [Genre::create(['name' => '文学'.uniqid()])->id];
        }

        $book = Book::create(array_merge([
            'user_id' => $user->id,
            'title' => 'テスト書籍',
            'author' => 'テスト著者',
            'isbn' => '9784'.str_pad((string) self::$isbnSequence++, 9, '0', STR_PAD_LEFT),
            'published_date' => '2020-01-01',
            'description' => '説明文です。',
            'image_url' => null,
        ], $overrides));

        $book->genres()->sync($genreIds);

        return $book;
    }
}
