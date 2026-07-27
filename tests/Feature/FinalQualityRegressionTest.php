<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Favorite;
use App\Models\Genre;
use App\Models\ReadingPlan;
use App\Models\Review;
use App\Models\ReviewLike;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class FinalQualityRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_database_counts_and_core_relations_match_final_quality_expectations(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(5, User::count());
        $this->assertSame(10, Genre::count());
        $this->assertSame(11, Book::count());
        $this->assertSame(32, Review::count());
        $this->assertSame(15, Favorite::count());
        $this->assertSame(24, ReviewLike::count());
        $this->assertSame(6, ReadingPlan::count());
        $this->assertDatabaseCount('notifications', 0);

        $book = Book::where('isbn', '9784309226712')->firstOrFail();
        $this->assertTrue($book->genres()->exists());
        $this->assertTrue($book->reviews()->exists());
        $this->assertTrue($book->readingPlans()->exists());
    }

    public function test_final_web_and_api_surface_remains_available_with_seeded_data(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = User::where('email', 'yamada@example.com')->firstOrFail();
        $book = Book::where('isbn', '9784101010014')->firstOrFail();
        $genre = Genre::where('name', '小説')->firstOrFail();

        foreach ([
            'home',
            'books.index',
            'books.show',
            'books.isbn-search',
            'reading-report.show',
            'notifications.index',
            'reading-plans.index',
            'favorites.index',
            'genres.index',
            'genres.show',
            'ranking.index',
            'api.tokens.store',
            'api.v1.books.index',
            'api.v1.books.show',
            'api.v1.books.store',
            'api.v1.books.update',
            'api.v1.books.destroy',
        ] as $routeName) {
            $this->assertTrue(Route::has($routeName), "{$routeName} route is missing.");
        }

        $this->get(route('home'))->assertOk();
        $this->get(route('books.show', $book))->assertOk();
        $this->get(route('ranking.index'))->assertOk();
        $this->getJson('/api/v1/books')->assertOk()->assertJsonStructure(['data', 'meta', 'links']);
        $this->getJson('/api/v1/books/'.$book->id)->assertOk()->assertJsonPath('data.id', $book->id);
        $this->postJson('/api/v1/books', [])->assertUnauthorized();

        $this->actingAs($user)->get(route('reading-report.show'))->assertOk();
        $this->actingAs($user)->get(route('notifications.index'))->assertOk();
        $this->actingAs($user)->get(route('reading-plans.index'))->assertOk();
        $this->actingAs($user)->get(route('favorites.index'))->assertOk();
        $this->actingAs($user)->get(route('genres.show', $genre))->assertOk();
    }
}
