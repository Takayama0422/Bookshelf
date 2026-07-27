<?php

namespace Tests\Feature\Console;

use App\Console\Kernel;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use App\Notifications\ReadingPlanReminderNotification;
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

    public function test_command_creates_three_day_due_and_overdue_notifications_and_expires_past_plans(): void
    {
        Carbon::setTestNow('2026-07-27 10:15:00');

        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        $threeDaysPlan = $this->createPlan($firstUser, today()->addDays(3));
        $duePlan = $this->createPlan($firstUser, today());
        $overduePlan = $this->createPlan($secondUser, today()->subDay());

        $this->artisan('reading-plans:process')
            ->expectsOutputToContain('expired=1 three_days=1 due=1 overdue=1')
            ->assertSuccessful();

        $threeDaysPlan->refresh();
        $duePlan->refresh();
        $overduePlan->refresh();

        $this->assertSame('2026-07-27 10:15:00', $threeDaysPlan->reminded_three_days_at->toDateTimeString());
        $this->assertSame('2026-07-27 10:15:00', $duePlan->reminded_due_at->toDateTimeString());
        $this->assertSame('2026-07-27 10:15:00', $overduePlan->reminded_overdue_at->toDateTimeString());
        $this->assertSame(ReadingPlan::STATUS_EXPIRED, $overduePlan->status);
        $this->assertSame('2026-07-27 10:15:00', $overduePlan->expired_at->toDateTimeString());
        $this->assertSame(2, $firstUser->notifications()->count());
        $this->assertSame(1, $secondUser->notifications()->count());

        $this->assertNotification($firstUser, $threeDaysPlan, ReadingPlanReminderNotification::TYPE_THREE_DAYS);
        $this->assertNotification($firstUser, $duePlan, ReadingPlanReminderNotification::TYPE_DUE);
        $this->assertNotification($secondUser, $overduePlan, ReadingPlanReminderNotification::TYPE_OVERDUE);
    }

    public function test_command_is_idempotent_and_does_not_confuse_reminder_types(): void
    {
        Carbon::setTestNow('2026-07-27 08:00:00');

        $user = User::factory()->create();
        $threeDaysPlan = $this->createPlan($user, today()->addDays(3));
        $duePlan = $this->createPlan($user, today());
        $overduePlan = $this->createPlan($user, today()->subDay());

        $this->artisan('reading-plans:process')->assertSuccessful();
        $this->artisan('reading-plans:process')
            ->expectsOutputToContain('expired=0 three_days=0 due=0 overdue=0')
            ->assertSuccessful();

        $this->assertSame(3, $user->notifications()->count());
        $this->assertSame(1, $this->notificationCount($user, $threeDaysPlan, ReadingPlanReminderNotification::TYPE_THREE_DAYS));
        $this->assertSame(1, $this->notificationCount($user, $duePlan, ReadingPlanReminderNotification::TYPE_DUE));
        $this->assertSame(1, $this->notificationCount($user, $overduePlan, ReadingPlanReminderNotification::TYPE_OVERDUE));
    }

    public function test_completed_and_expired_plans_are_not_changed(): void
    {
        Carbon::setTestNow('2026-07-27 12:00:00');

        $completedAt = Carbon::parse('2026-07-20 09:00:00');
        $expiredAt = Carbon::parse('2026-07-21 09:00:00');

        $completedPlan = $this->createPlan(User::factory()->create(), today()->subDays(2), [
            'status' => ReadingPlan::STATUS_COMPLETED,
            'completed_at' => $completedAt,
        ]);
        $expiredPlan = $this->createPlan(User::factory()->create(), today()->subDays(3), [
            'status' => ReadingPlan::STATUS_EXPIRED,
            'expired_at' => $expiredAt,
        ]);

        $this->artisan('reading-plans:process')
            ->expectsOutputToContain('expired=0 three_days=0 due=0 overdue=0')
            ->assertSuccessful();

        $completedPlan->refresh();
        $expiredPlan->refresh();

        $this->assertSame(ReadingPlan::STATUS_COMPLETED, $completedPlan->status);
        $this->assertSame('2026-07-20 09:00:00', $completedPlan->completed_at->toDateTimeString());
        $this->assertNull($completedPlan->expired_at);
        $this->assertSame(ReadingPlan::STATUS_EXPIRED, $expiredPlan->status);
        $this->assertSame('2026-07-21 09:00:00', $expiredPlan->expired_at->toDateTimeString());
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_boundary_dates_are_processed_only_at_exact_timing(): void
    {
        Carbon::setTestNow('2026-07-27 00:00:00');

        $user = User::factory()->create();
        $twoDaysPlan = $this->createPlan($user, today()->addDays(2));
        $threeDaysPlan = $this->createPlan($user, today()->addDays(3));
        $tomorrowPlan = $this->createPlan($user, today()->addDay());
        $duePlan = $this->createPlan($user, today());
        $overduePlan = $this->createPlan($user, today()->subDay());

        $this->artisan('reading-plans:process')->assertSuccessful();

        $this->assertNull($twoDaysPlan->refresh()->reminded_three_days_at);
        $this->assertNotNull($threeDaysPlan->refresh()->reminded_three_days_at);
        $this->assertNull($tomorrowPlan->refresh()->reminded_due_at);
        $this->assertNotNull($duePlan->refresh()->reminded_due_at);
        $this->assertSame(ReadingPlan::STATUS_EXPIRED, $overduePlan->refresh()->status);
        $this->assertSame(3, $user->notifications()->count());
    }

    public function test_existing_reminded_columns_prevent_duplicate_notifications_independently(): void
    {
        Carbon::setTestNow('2026-07-27 07:30:00');

        $user = User::factory()->create();
        $alreadyThreeDays = $this->createPlan($user, today()->addDays(3), [
            'reminded_three_days_at' => now()->subDay(),
        ]);
        $alreadyDue = $this->createPlan($user, today(), [
            'reminded_due_at' => now()->subDay(),
        ]);
        $alreadyOverdue = $this->createPlan($user, today()->subDay(), [
            'reminded_overdue_at' => now()->subDay(),
        ]);

        $this->artisan('reading-plans:process')->assertSuccessful();

        $this->assertSame('2026-07-26 07:30:00', $alreadyThreeDays->refresh()->reminded_three_days_at->toDateTimeString());
        $this->assertSame('2026-07-26 07:30:00', $alreadyDue->refresh()->reminded_due_at->toDateTimeString());
        $this->assertSame('2026-07-26 07:30:00', $alreadyOverdue->refresh()->reminded_overdue_at->toDateTimeString());
        $this->assertSame(ReadingPlan::STATUS_EXPIRED, $alreadyOverdue->status);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_reminded_timestamp_is_not_saved_when_notification_is_not_created(): void
    {
        Carbon::setTestNow('2026-07-27 10:00:00');
        Notification::fake();

        $plan = $this->createPlan(User::factory()->create(), today()->addDays(3));

        $this->artisan('reading-plans:process')->assertSuccessful();

        $this->assertNull($plan->refresh()->reminded_three_days_at);
        $this->assertDatabaseCount('notifications', 0);
        Notification::assertSentTo($plan->user, ReadingPlanReminderNotification::class);
    }

    public function test_scheduler_registers_daily_command_without_overlapping(): void
    {
        $schedule = app(Schedule::class);
        $kernel = app(Kernel::class);
        $method = new ReflectionMethod($kernel, 'schedule');
        $method->setAccessible(true);
        $method->invoke($kernel, $schedule);

        $event = collect($schedule->events())->first(fn ($event) => str_contains((string) $event->command, 'reading-plans:process'));

        $this->assertNotNull($event);
        $this->assertSame('0 0 * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
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
            'status' => ReadingPlan::STATUS_IN_PROGRESS,
        ], $attributes));
    }

    private function assertNotification(User $user, ReadingPlan $plan, string $type): void
    {
        $this->assertSame(1, $this->notificationCount($user, $plan, $type));
    }

    private function notificationCount(User $user, ReadingPlan $plan, string $type): int
    {
        return $user->notifications()
            ->get()
            ->filter(fn ($notification): bool => data_get($notification->data, 'plan_id') === $plan->id
                && data_get($notification->data, 'book_id') === $plan->book_id
                && data_get($notification->data, 'notification_type') === $type)
            ->count();
    }
}
