<?php

namespace App\Notifications;

use App\Models\ReadingPlan;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ReadingPlanReminderNotification extends Notification
{
    use Queueable;

    public const TYPE_THREE_DAYS = 'reading_plan_three_days';

    public const TYPE_DUE = 'reading_plan_due';

    public const TYPE_OVERDUE = 'reading_plan_overdue';

    public function __construct(
        private readonly ReadingPlan $readingPlan,
        private readonly string $reminderType,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message' => $this->message(),
            'notification_type' => $this->reminderType,
            'plan_id' => $this->readingPlan->id,
            'book_id' => $this->readingPlan->book_id,
        ];
    }

    private function message(): string
    {
        return match ($this->reminderType) {
            self::TYPE_THREE_DAYS => '読書計画の目標読了日まであと3日です。',
            self::TYPE_DUE => '読書計画の目標読了日は今日です。',
            self::TYPE_OVERDUE => '読書計画の目標読了日を過ぎています。',
            default => '読書計画の期限通知です。',
        };
    }
}
