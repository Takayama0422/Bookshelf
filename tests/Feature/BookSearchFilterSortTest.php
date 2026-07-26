<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookSearchFilterSortTest extends TestCase
{
    use RefreshDatabase;

    private static int $isbnSequence = 1;

    public function test_guests_can_search_books_by_keyword_in_title(): void
    {
        $this->createBook(['title' => 'Laravel検索入門']);
        $this->createBook(['title' => 'PHP実践']);

        $this->get(route('books.index', ['keyword' => '検索']))
            ->assertOk()
            ->assertSee('Laravel検索入門')
            ->assertDontSee('PHP実践')
            ->assertSee('value="検索"', false);
    }

    public function test_guests_can_search_books_by_keyword_in_author(): void
    {
        $this->createBook(['title' => '設計の本', 'author' => '検索太郎']);
        $this->createBook(['title' => '別の本', 'author' => '別著者']);

        $this->get(route('books.index', ['keyword' => '検索太郎']))
            ->assertOk()
            ->assertSee('設計の本')
            ->assertDontSee('別の本');
    }

    public function test_guests_can_filter_books_by_genre(): void
    {
        $targetGenre = Genre::create(['name' => '技術書']);
        $otherGenre = Genre::create(['name' => '文学']);

        $this->createBook(['title' => 'Laravel実践'], [$targetGenre->id]);
        $this->createBook(['title' => '文学作品'], [$otherGenre->id]);

        $this->get(route('books.index', ['genre' => $targetGenre->id]))
            ->assertOk()
            ->assertSee('Laravel実践')
            ->assertDontSee('文学作品')
            ->assertSee('value="'.$targetGenre->id.'" selected', false);
    }

    public function test_guests_can_sort_books_by_selected_condition(): void
    {
        $newBook = $this->createBook(['title' => '新しい書籍']);
        $oldBook = $this->createBook(['title' => '古い書籍']);
        $titleFirstBook = $this->createBook(['title' => 'Aタイトル']);
        $ratingTopBook = $this->createBook(['title' => '高評価書籍']);
        $unreviewedBook = $this->createBook(['title' => 'レビューなし書籍']);

        $oldBook->forceFill(['created_at' => now()->subDays(2)])->save();
        $newBook->forceFill(['created_at' => now()->subDay()])->save();
        $titleFirstBook->forceFill(['created_at' => now()])->save();

        $this->createReview($ratingTopBook, 5);
        $this->createReview($oldBook, 3);

        $this->get(route('books.index', ['sort' => 'oldest']))
            ->assertOk()
            ->assertSeeInOrder(['古い書籍', '新しい書籍']);

        $this->get(route('books.index', ['sort' => 'title']))
            ->assertOk()
            ->assertSeeInOrder(['Aタイトル', 'レビューなし書籍']);

        $this->get(route('books.index', ['sort' => 'rating']))
            ->assertOk()
            ->assertSeeInOrder(['高評価書籍', '古い書籍', 'レビューなし書籍']);

        $this->assertDatabaseHas('books', ['id' => $unreviewedBook->id]);
    }

    public function test_invalid_sort_value_is_rejected_safely(): void
    {
        $this->createBook(['title' => '安全確認書籍']);

        $this->from(route('books.index'))
            ->get(route('books.index', ['sort' => 'created_at']))
            ->assertRedirect(route('books.index'))
            ->assertSessionHasErrors('sort');
    }

    public function test_guests_can_combine_keyword_genre_and_sort_conditions(): void
    {
        $targetGenre = Genre::create(['name' => '技術書']);
        $otherGenre = Genre::create(['name' => '文学']);

        $olderTarget = $this->createBook(['title' => 'Laravel基礎', 'author' => '山田'], [$targetGenre->id]);
        $newerTarget = $this->createBook(['title' => 'Laravel応用', 'author' => '佐藤'], [$targetGenre->id]);
        $this->createBook(['title' => 'Laravel小説', 'author' => '田中'], [$otherGenre->id]);

        $olderTarget->forceFill(['created_at' => now()->subDays(2)])->save();
        $newerTarget->forceFill(['created_at' => now()->subDay()])->save();

        $this->get(route('books.index', [
            'keyword' => 'Laravel',
            'genre' => $targetGenre->id,
            'sort' => 'oldest',
        ]))
            ->assertOk()
            ->assertSeeInOrder(['Laravel基礎', 'Laravel応用'])
            ->assertDontSee('Laravel小説');
    }

    public function test_pagination_links_keep_search_conditions(): void
    {
        $genre = Genre::create(['name' => '技術書']);

        for ($i = 1; $i <= 11; $i++) {
            $book = $this->createBook([
                'title' => sprintf('検索対象%02d', $i),
                'author' => '対象著者',
            ], [$genre->id]);
            $book->forceFill(['created_at' => now()->addMinutes($i)])->save();
        }

        $this->get(route('books.index', [
            'keyword' => '検索対象',
            'genre' => $genre->id,
            'sort' => 'oldest',
        ]))
            ->assertOk()
            ->assertSee('keyword=%E6%A4%9C%E7%B4%A2%E5%AF%BE%E8%B1%A1', false)
            ->assertSee('genre='.$genre->id, false)
            ->assertSee('sort=oldest', false);
    }

    public function test_existing_book_index_still_displays_books(): void
    {
        $book = $this->createBook(['title' => '通常一覧書籍']);

        $this->get(route('books.index'))
            ->assertOk()
            ->assertSee($book->title)
            ->assertSee('キーワード')
            ->assertSee('ジャンル')
            ->assertSee('並び順');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @param  array<int, int>  $genreIds
     */
    private function createBook(array $overrides = [], array $genreIds = []): Book
    {
        $user = User::factory()->create();

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

    private function createReview(Book $book, int $rating): Review
    {
        return Review::create([
            'user_id' => User::factory()->create()->id,
            'book_id' => $book->id,
            'rating' => $rating,
            'comment' => '検索ソート確認用レビューです。',
        ]);
    }
}
