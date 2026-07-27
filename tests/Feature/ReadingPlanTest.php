<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReadingPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_reading_plan(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $this->actingAs($user)
            ->post(route('reading-plans.store'), [
                'book_id' => $book->id,
                'target_date' => today()->toDateString(),
                'user_id' => User::factory()->create()->id,
                'status' => ReadingPlan::STATUS_COMPLETED,
            ])
            ->assertRedirect(route('reading-plans.index'));

        $this->assertDatabaseHas('reading_plans', [
            'user_id' => $user->id,
            'book_id' => $book->id,
            'target_date' => today()->toDateString(),
            'status' => ReadingPlan::STATUS_IN_PROGRESS,
        ]);

        $this->assertDatabaseMissing('reading_plans', [
            'user_id' => $user->id,
            'book_id' => $book->id,
            'status' => ReadingPlan::STATUS_COMPLETED,
        ]);
    }

    public function test_guest_cannot_use_reading_plan_routes(): void
    {
        $plan = ReadingPlan::factory()->create();

        $this->get(route('reading-plans.index'))->assertRedirect('/login');
        $this->get(route('reading-plans.create'))->assertRedirect('/login');
        $this->post(route('reading-plans.store'))->assertRedirect('/login');
        $this->get(route('reading-plans.edit', $plan))->assertRedirect('/login');
        $this->put(route('reading-plans.update', $plan))->assertRedirect('/login');
        $this->post(route('reading-plans.complete', $plan))->assertRedirect('/login');
        $this->delete(route('reading-plans.destroy', $plan))->assertRedirect('/login');
    }

    public function test_index_displays_only_authenticated_users_reading_plans(): void
    {
        $user = User::factory()->create();
        $ownBook = Book::factory()->create(['title' => '自分の読書計画本']);
        $otherBook = Book::factory()->create(['title' => '他人の読書計画本']);

        ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'book_id' => $ownBook->id,
        ]);
        ReadingPlan::factory()->create([
            'book_id' => $otherBook->id,
        ]);

        $this->actingAs($user)
            ->get(route('reading-plans.index'))
            ->assertOk()
            ->assertSee('自分の読書計画本')
            ->assertDontSee('他人の読書計画本');
    }

    public function test_index_can_filter_by_status_and_displays_all_statuses(): void
    {
        $user = User::factory()->create();
        $inProgressBook = Book::factory()->create(['title' => '進行中の本']);
        $completedBook = Book::factory()->create(['title' => '読了した本']);
        $expiredBook = Book::factory()->create(['title' => '期限切れの本']);

        ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'book_id' => $inProgressBook->id,
            'status' => ReadingPlan::STATUS_IN_PROGRESS,
        ]);
        ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'book_id' => $completedBook->id,
            'status' => ReadingPlan::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
        ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'book_id' => $expiredBook->id,
            'status' => ReadingPlan::STATUS_EXPIRED,
            'expired_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('reading-plans.index', ['status' => ReadingPlan::STATUS_COMPLETED]))
            ->assertOk()
            ->assertSee('読了した本')
            ->assertSee('読了')
            ->assertDontSee('進行中の本')
            ->assertDontSee('期限切れの本');
    }

    public function test_reading_plan_can_be_updated_without_editing_status(): void
    {
        $user = User::factory()->create();
        $firstBook = Book::factory()->create();
        $secondBook = Book::factory()->create(['title' => '変更後の本']);
        $plan = ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'book_id' => $firstBook->id,
            'target_date' => today()->addDays(3),
            'status' => ReadingPlan::STATUS_IN_PROGRESS,
        ]);

        $this->actingAs($user)
            ->put(route('reading-plans.update', $plan), [
                'book_id' => $secondBook->id,
                'target_date' => today()->addDays(10)->toDateString(),
                'status' => ReadingPlan::STATUS_COMPLETED,
            ])
            ->assertRedirect(route('reading-plans.index'));

        $this->assertDatabaseHas('reading_plans', [
            'id' => $plan->id,
            'book_id' => $secondBook->id,
            'target_date' => today()->addDays(10)->toDateString(),
            'status' => ReadingPlan::STATUS_IN_PROGRESS,
        ]);
    }

    public function test_complete_updates_status_and_completed_at(): void
    {
        Carbon::setTestNow('2026-07-27 10:00:00');

        $user = User::factory()->create();
        $plan = ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'status' => ReadingPlan::STATUS_IN_PROGRESS,
            'completed_at' => null,
            'expired_at' => null,
        ]);

        $this->actingAs($user)
            ->post(route('reading-plans.complete', $plan))
            ->assertRedirect(route('reading-plans.index'));

        $plan->refresh();

        $this->assertSame(ReadingPlan::STATUS_COMPLETED, $plan->status);
        $this->assertSame('2026-07-27 10:00:00', $plan->completed_at->toDateTimeString());
        $this->assertNull($plan->expired_at);

        Carbon::setTestNow();
    }

    public function test_reading_plan_can_be_deleted(): void
    {
        $user = User::factory()->create();
        $plan = ReadingPlan::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->delete(route('reading-plans.destroy', $plan))
            ->assertRedirect(route('reading-plans.index'));

        $this->assertDatabaseMissing('reading_plans', ['id' => $plan->id]);
    }

    public function test_other_user_cannot_update_complete_or_delete_reading_plan(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $plan = ReadingPlan::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($otherUser)
            ->get(route('reading-plans.edit', $plan))
            ->assertForbidden();

        $this->actingAs($otherUser)
            ->put(route('reading-plans.update', $plan), [
                'book_id' => $plan->book_id,
                'target_date' => today()->addDays(5)->toDateString(),
            ])
            ->assertForbidden();

        $this->actingAs($otherUser)
            ->post(route('reading-plans.complete', $plan))
            ->assertForbidden();

        $this->actingAs($otherUser)
            ->delete(route('reading-plans.destroy', $plan))
            ->assertForbidden();
    }

    public function test_missing_book_invalid_date_and_invalid_status_are_rejected(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $this->actingAs($user)
            ->post(route('reading-plans.store'), [
                'book_id' => 999999,
                'target_date' => today()->toDateString(),
            ])
            ->assertSessionHasErrors('book_id');

        $this->actingAs($user)
            ->post(route('reading-plans.store'), [
                'book_id' => $book->id,
                'target_date' => today()->subDay()->toDateString(),
            ])
            ->assertSessionHasErrors('target_date');

        $this->actingAs($user)
            ->get(route('reading-plans.index', ['status' => 'pending']))
            ->assertSessionHasErrors('status');
    }

    public function test_in_progress_duplicate_is_rejected_but_completed_and_expired_plans_can_be_created_again(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'status' => ReadingPlan::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
        ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'status' => ReadingPlan::STATUS_EXPIRED,
            'expired_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('reading-plans.store'), [
                'book_id' => $book->id,
                'target_date' => today()->addDays(7)->toDateString(),
            ])
            ->assertRedirect(route('reading-plans.index'));

        $this->assertSame(1, ReadingPlan::where('user_id', $user->id)
            ->where('book_id', $book->id)
            ->where('status', ReadingPlan::STATUS_IN_PROGRESS)
            ->count());

        $this->actingAs($user)
            ->post(route('reading-plans.store'), [
                'book_id' => $book->id,
                'target_date' => today()->addDays(8)->toDateString(),
            ])
            ->assertSessionHasErrors('book_id');

        $this->assertSame(1, ReadingPlan::where('user_id', $user->id)
            ->where('book_id', $book->id)
            ->where('status', ReadingPlan::STATUS_IN_PROGRESS)
            ->count());
    }

    public function test_empty_index_displays_fixed_empty_message(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('reading-plans.index'))
            ->assertOk()
            ->assertSee('読書計画が登録されていません。');
    }
}
