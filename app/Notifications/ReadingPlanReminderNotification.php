<?php

namespace App\Notifications;

use App\Models\ReadingPlan;

/**
 * 旧クラス名から正本の通知へ移行するための互換ラッパー。
 */
class ReadingPlanReminderNotification extends PlanReminderNotification
{
    public const TYPE_THREE_DAYS = PlanReminderNotification::TIMING_THREE_DAYS_BEFORE;

    public const TYPE_DUE = PlanReminderNotification::TIMING_DUE_TODAY;

    public const TYPE_OVERDUE = PlanReminderNotification::TIMING_THREE_DAYS_AFTER;

    public function __construct(ReadingPlan $readingPlan, string $reminderType)
    {
        parent::__construct($readingPlan, $reminderType);
    }

    /**
     * 旧payloadの読み手向けに互換キーを加える。
     *
     * @param  object  $notifiable  通知受信者
     * @return array<string, mixed> 通知payload
     */
    public function toArray(object $notifiable): array
    {
        $payload = parent::toArray($notifiable);

        return array_merge($payload, [
            'message' => $payload['body'],
            'notification_type' => $payload['timing'],
            'book_id' => $this->readingPlan->book_id,
        ]);
    }
}
