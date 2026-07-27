<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_notification_index(): void
    {
        $user = User::factory()->create();
        $notification = $this->createNotification($user, [
            'message' => '読書計画の期限が近づいています。',
            'notification_type' => 'due_soon',
            'plan_id' => 123,
        ]);

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('通知一覧')
            ->assertSee('読書計画の期限が近づいています。')
            ->assertSee('due_soon')
            ->assertSee('123')
            ->assertSee(route('notifications.read', $notification), false);

        $this->assertSame('/notifications', route('notifications.index', absolute: false));
        $this->assertSame("/notifications/{$notification->id}/read", route('notifications.read', $notification, false));
    }

    public function test_guest_cannot_use_notification_routes(): void
    {
        $notification = $this->createNotification(User::factory()->create());

        $this->get(route('notifications.index'))->assertRedirect('/login');
        $this->post(route('notifications.read', $notification))->assertRedirect('/login');
    }

    public function test_index_displays_only_authenticated_users_notifications(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->createNotification($user, ['message' => '自分宛ての通知']);
        $this->createNotification($otherUser, ['message' => '他ユーザー宛ての通知']);

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('自分宛ての通知')
            ->assertDontSee('他ユーザー宛ての通知');
    }

    public function test_unread_and_read_notifications_are_displayed_with_each_status(): void
    {
        $user = User::factory()->create();

        $this->createNotification($user, ['message' => '未読の通知'], ['read_at' => null]);
        $this->createNotification($user, ['message' => '既読の通知'], ['read_at' => now()]);

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('未読の通知')
            ->assertSee('未読')
            ->assertSee('既読の通知')
            ->assertSee('既読')
            ->assertSee('既読にする')
            ->assertSee('既読済み');
    }

    public function test_user_can_mark_own_unread_notification_as_read(): void
    {
        Carbon::setTestNow('2026-07-27 10:00:00');

        $user = User::factory()->create();
        $notification = $this->createNotification($user, ['message' => '既読化する通知']);

        $this->actingAs($user)
            ->post(route('notifications.read', $notification))
            ->assertRedirect(route('notifications.index'))
            ->assertSessionHas('success', '通知を既読にしました。');

        $notification->refresh();

        $this->assertSame('2026-07-27 10:00:00', $notification->read_at->toDateTimeString());

        Carbon::setTestNow();
    }

    public function test_marking_already_read_notification_again_does_not_fail(): void
    {
        $user = User::factory()->create();
        $readAt = Carbon::parse('2026-07-27 09:00:00');
        $notification = $this->createNotification($user, ['message' => '既読済み通知'], [
            'read_at' => $readAt,
        ]);

        $this->actingAs($user)
            ->post(route('notifications.read', $notification))
            ->assertRedirect(route('notifications.index'));

        $notification->refresh();

        $this->assertSame('2026-07-27 09:00:00', $notification->read_at->toDateTimeString());
    }

    public function test_other_users_notification_cannot_be_marked_as_read(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $notification = $this->createNotification($owner, ['message' => '他ユーザーの通知']);

        $this->actingAs($otherUser)
            ->post(route('notifications.read', $notification))
            ->assertForbidden();

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'read_at' => null,
        ]);
    }

    public function test_empty_notification_index_displays_empty_state(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('通知はありません。');
    }

    public function test_notification_payload_is_displayed_without_missing_key_errors(): void
    {
        $user = User::factory()->create();

        $this->createNotification($user, [
            'body' => 'bodyキーの通知本文',
            'plan_id' => 456,
        ]);
        $this->createNotification($user, []);

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('bodyキーの通知本文')
            ->assertSee('456')
            ->assertSee('通知内容はありません。');
    }

    public function test_notification_index_is_paginated_by_ten_notifications(): void
    {
        $user = User::factory()->create();

        for ($i = 1; $i <= 11; $i++) {
            $this->createNotification($user, ['message' => sprintf('通知%02d', $i)], [
                'created_at' => now()->addMinutes($i),
                'updated_at' => now()->addMinutes($i),
            ]);
        }

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('通知11')
            ->assertSee('通知02')
            ->assertDontSee('通知01');

        $this->actingAs($user)
            ->get(route('notifications.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('通知01')
            ->assertDontSee('通知11');
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $overrides
     */
    private function createNotification(User $user, array $data = [], array $overrides = []): DatabaseNotification
    {
        return DatabaseNotification::query()->create(array_merge([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\ReadingPlanReminderNotification',
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->id,
            'data' => $data,
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }
}
