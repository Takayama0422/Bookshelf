<?php

namespace App\Notifications;

use App\Models\ReadingPlan;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PlanReminderNotification extends Notification
{
    use Queueable;

    public const TIMING_THREE_DAYS_BEFORE = 'three_days_before';

    public const TIMING_DUE_TODAY = 'due_today';

    public const TIMING_THREE_DAYS_AFTER = 'three_days_after';

    public function __construct(
        protected readonly ReadingPlan $readingPlan,
        protected readonly string $timing,
    ) {}

    /**
     * Database通知だけを使用する。
     *
     * @param  object  $notifiable  通知受信者
     * @return list<string> 配信チャネル
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * 正本の通知データを生成する。
     *
     * @param  object  $notifiable  通知受信者
     * @return array<string, int|string> 通知payload
     */
    public function toDatabase(object $notifiable): array
    {
        [$title, $body] = match ($this->timing) {
            self::TIMING_THREE_DAYS_BEFORE => [
                '読書計画リマインド — 期限まであと 3 日',
                '「'.$this->readingPlan->book->title.'」の期限まで残り 3 日です。引き続き読書を進めましょう。',
            ],
            self::TIMING_DUE_TODAY => [
                '読書計画 — 本日が期限',
                '「'.$this->readingPlan->book->title.'」は本日が期限です。読了済みなら完了登録を、もう少し必要なら期限を変更してください。',
            ],
            self::TIMING_THREE_DAYS_AFTER => [
                '読書計画 — 期限超過 3 日経過',
                '「'.$this->readingPlan->book->title.'」の期限から 3 日が経過しました。読了済みなら完了登録、続けるなら期限を変更してください。',
            ],
        };

        return [
            'plan_id' => $this->readingPlan->id,
            'book_title' => $this->readingPlan->book->title,
            'timing' => $this->timing,
            'title' => $title,
            'body' => $body,
        ];
    }

    /**
     * DatabaseChannelがtoDatabaseを使わない構成でも同じpayloadを保存する。
     *
     * @param  object  $notifiable  通知受信者
     * @return array<string, int|string> 通知payload
     */
    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
