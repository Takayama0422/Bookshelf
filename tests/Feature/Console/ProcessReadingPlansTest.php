<?php

namespace Tests\Feature\Console;

use App\Console\Kernel;
use App\Enums\ReadingPlanStatus;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use App\Notifications\PlanReminderNotification;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use ReflectionMethod;
use Tests\TestCase;

class ProcessReadingPlansTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_daily_command_expires_past_plans_and_notifies_at_three_timings(): void
    {
        Carbon::setTestNow('2026-07-27 10:15:00');
        $user = User::factory()->create();

        $before = $this->createPlan($user, today()->addDays(3));
        $today = $this->createPlan($user, today());
        $after = $this->createPlan($user, today()->subDays(3), [
            'status' => ReadingPlanStatus::Expired,
        ]);
        $toExpire = $this->createPlan($user, today()->subDay());

        $this->artisan('reading-plans:run-daily')
            ->expectsOutputToContain('expired=1 three_days_before=1 due_today=1 three_days_after=1')
            ->assertSuccessful();

        $this->assertNotNull($before->refresh()->reminded_three_days_at);
        $this->assertNotNull($today->refresh()->reminded_due_at);
        $this->assertNotNull($after->refresh()->reminded_overdue_at);
        $this->assertSame(ReadingPlanStatus::Expired, $toExpire->refresh()->status);
        $this->assertNull($toExpire->reminded_overdue_at);
        $this->assertSame(3, $user->notifications()->count());
    }

    public function test_daily_command_is_idempotent(): void
    {
        Carbon::setTestNow('2026-07-27 08:00:00');
        $user = User::factory()->create();
        $plan = $this->createPlan($user, today());

        $this->artisan('reading-plans:run-daily')->assertSuccessful();
        $this->artisan('reading-plans:run-daily')
            ->expectsOutputToContain('expired=0 three_days_before=0 due_today=0 three_days_after=0')
            ->assertSuccessful();

        $this->assertSame(1, $user->notifications()->count());
        $this->assertNotNull($plan->refresh()->reminded_due_at);
    }

    public function test_notification_timestamp_is_not_saved_when_database_channel_is_faked(): void
    {
        Carbon::setTestNow('2026-07-27 10:00:00');
        Notification::fake();
        $plan = $this->createPlan(User::factory()->create(), today()->addDays(3));

        $this->artisan('reading-plans:run-daily')->assertSuccessful();

        $this->assertNull($plan->refresh()->reminded_three_days_at);
        Notification::assertSentTo($plan->user, PlanReminderNotification::class);
    }

    public function test_scheduler_runs_the_required_command_at_twenty(): void
    {
        $schedule = app(Schedule::class);
        $kernel = app(Kernel::class);
        $method = new ReflectionMethod($kernel, 'schedule');
        $method->setAccessible(true);
        $method->invoke($kernel, $schedule);

        $event = collect($schedule->events())
            ->first(fn ($event) => str_contains((string) $event->command, 'reading-plans:run-daily'));

        $this->assertNotNull($event);
        $this->assertSame('0 20 * * *', $event->expression);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createPlan(User $user, Carbon $targetDate, array $attributes = []): ReadingPlan
    {
        return ReadingPlan::factory()->create(array_merge([
            'user_id' => $user->id,
            'book_id' => Book::factory()->create()->id,
            'target_date' => $targetDate->toDateString(),
            'status' => ReadingPlanStatus::InProgress,
        ], $attributes));
    }
}
