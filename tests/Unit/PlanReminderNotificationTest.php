<?php

namespace Tests\Unit;

use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use App\Notifications\PlanReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanReminderNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_payload_contains_the_canonical_reading_plan_fields(): void
    {
        $plan = ReadingPlan::factory()->create([
            'user_id' => User::factory()->create()->id,
            'book_id' => Book::factory()->create()->id,
        ]);

        $payload = (new PlanReminderNotification(
            $plan,
            PlanReminderNotification::TIMING_DUE_TODAY,
        ))->toArray($plan->user);

        $this->assertSame([
            'plan_id',
            'book_title',
            'timing',
            'title',
            'body',
        ], array_keys($payload));
        $this->assertSame($plan->id, $payload['plan_id']);
        $this->assertSame($plan->book->title, $payload['book_title']);
        $this->assertSame(PlanReminderNotification::TIMING_DUE_TODAY, $payload['timing']);
        $this->assertSame('読書計画 — 本日が期限', $payload['title']);
    }
}
