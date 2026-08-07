<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\Review;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdvancedDataFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_reading_plans_schema_and_seed_data_match_the_advanced_specification(): void
    {
        Carbon::setTestNow('2026-07-26 10:00:00');
        $this->seed(DatabaseSeeder::class);

        $this->assertTrue(Schema::hasColumns('reading_plans', [
            'user_id',
            'book_id',
            'target_date',
            'status',
            'completed_at',
            'expired_at',
            'reminded_three_days_at',
            'reminded_due_at',
            'reminded_overdue_at',
        ]));
        $this->assertSame(6, ReadingPlan::count());
        $this->assertSame(2, ReadingPlan::where('status', 'in_progress')->count());
        $this->assertSame(2, ReadingPlan::where('status', 'completed')->count());
        $this->assertSame(2, ReadingPlan::where('status', 'expired')->count());
        $this->assertSame(0, ReadingPlan::whereNotNull('reminded_three_days_at')->count());
        $this->assertSame(0, ReadingPlan::whereNotNull('reminded_due_at')->count());
        $this->assertSame(0, ReadingPlan::whereNotNull('reminded_overdue_at')->count());

        $inProgress = ReadingPlan::where('status', 'in_progress')->firstOrFail();
        $this->assertSame('2026-07-29', $inProgress->target_date->toDateString());
        $this->assertNull($inProgress->completed_at);
        $this->assertNull($inProgress->expired_at);
        $this->assertSame('2026-07-12 00:00:00', $inProgress->created_at->toDateTimeString());
    }

    public function test_advanced_book_columns_allow_nullable_isbn_and_published_date(): void
    {
        $user = User::factory()->create();

        $book = Book::create([
            'user_id' => $user->id,
            'title' => 'ISBN未設定の本',
            'author' => '著者',
            'isbn' => null,
            'published_date' => null,
            'description' => null,
            'image_url' => null,
        ]);

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'isbn' => null,
            'published_date' => null,
        ]);
        $this->assertNull($book->fresh()->published_date);
    }

    public function test_advanced_seeder_is_idempotent_and_preserves_basic_data(): void
    {
        Carbon::setTestNow('2026-07-26 10:00:00');
        $this->seed(DatabaseSeeder::class);
        $targetDates = ReadingPlan::query()->orderBy('id')->pluck('target_date')->map(fn ($date) => (string) $date)->all();
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(5, User::count());
        $this->assertSame(11, Book::count());
        $this->assertGreaterThanOrEqual(22, Review::count());
        $this->assertLessThanOrEqual(44, Review::count());
        $this->assertSame(6, ReadingPlan::count());
        $this->assertSame($targetDates, ReadingPlan::query()->orderBy('id')->pluck('target_date')->map(fn ($date) => (string) $date)->all());
    }

    public function test_reading_plan_relations_and_user_book_cascades_are_defined(): void
    {
        $this->seed(DatabaseSeeder::class);

        $plan = ReadingPlan::whereHas('user', fn ($query) => $query->where('email', 'yamada@example.com'))
            ->whereHas('book', fn ($query) => $query->where('isbn', '9784309226712'))
            ->firstOrFail();

        $this->assertTrue($plan->user->is(User::where('email', 'yamada@example.com')->firstOrFail()));
        $this->assertTrue($plan->book->is(Book::where('isbn', '9784309226712')->firstOrFail()));
        $this->assertTrue($plan->user->readingPlans->contains($plan));
        $this->assertTrue($plan->book->readingPlans->contains($plan));

        $plan->user->delete();
        $this->assertDatabaseMissing('reading_plans', ['id' => $plan->id]);
    }

    public function test_empty_database_has_a_valid_empty_reading_plan_foundation(): void
    {
        $this->assertSame(0, ReadingPlan::count());
        $this->assertTrue(Schema::hasTable('reading_plans'));
    }
}
