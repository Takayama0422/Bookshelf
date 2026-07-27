<?php

namespace Tests\Unit;

use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use App\Notifications\ReadingPlanReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadingPlanReminderNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_payload_contains_minimum_reading_plan_identifiers_and_type(): void
    {
        $plan = ReadingPlan::factory()->create([
            'user_id' => User::factory()->create()->id,
            'book_id' => Book::factory()->create()->id,
        ]);

        $payload = (new ReadingPlanReminderNotification(
            $plan,
            ReadingPlanReminderNotification::TYPE_DUE,
        ))->toArray($plan->user);

        $this->assertSame($plan->id, $payload['plan_id']);
        $this->assertSame($plan->book_id, $payload['book_id']);
        $this->assertSame(ReadingPlanReminderNotification::TYPE_DUE, $payload['notification_type']);
        $this->assertSame('読書計画の目標読了日は今日です。', $payload['message']);
    }
}
