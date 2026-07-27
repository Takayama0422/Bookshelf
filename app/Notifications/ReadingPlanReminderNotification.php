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

    /**
     * 読書計画と通知種別を保持するDatabase通知を生成する。
     *
     * @param  ReadingPlan  $readingPlan  通知対象の読書計画
     * @param  string  $reminderType  3日前・当日・期限超過の通知種別
     */
    public function __construct(
        private readonly ReadingPlan $readingPlan,
        private readonly string $reminderType,
    ) {}

    /**
     * 通知の配信先としてDatabase channelを指定する。
     *
     * @param  object  $notifiable  通知を受け取るユーザー
     * @return list<string> Database channelの一覧
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Database通知へ保存するメッセージ、通知種別、計画ID、書籍IDを生成する。
     *
     * @param  object  $notifiable  通知を受け取るユーザー
     * @return array<string, mixed> Database channelへ保存する通知payload
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

    /**
     * 通知種別に対応する期限メッセージを返す。
     *
     * @return string 3日前・当日・期限超過、または既定の通知メッセージ
     */
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
