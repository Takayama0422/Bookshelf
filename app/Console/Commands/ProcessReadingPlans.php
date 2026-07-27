<?php

namespace App\Console\Commands;

use App\Enums\ReadingPlanStatus;
use App\Models\ReadingPlan;
use App\Notifications\ReadingPlanReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class ProcessReadingPlans extends Command
{
    protected $signature = 'reading-plans:process';

    protected $description = 'Process reading plan expiration and reminder notifications.';

    /**
     * @var array<string, int>
     */
    private array $counts = [
        'expired' => 0,
        'three_days' => 0,
        'due' => 0,
        'overdue' => 0,
    ];

    /**
     * 3日前・当日・期限超過の読書計画を処理し、通知数と失効数を出力する。
     *
     * 対象計画の通知日時を更新し、期限超過計画を失効状態へ遷移させる。
     *
     * @return int コマンドの成功終了コード
     */
    public function handle(): int
    {
        $this->counts = [
            'expired' => 0,
            'three_days' => 0,
            'due' => 0,
            'overdue' => 0,
        ];

        $today = Carbon::today();
        $now = Carbon::now();

        $this->processReminder(
            targetDate: $today->copy()->addDays(3),
            remindedColumn: 'reminded_three_days_at',
            reminderType: ReadingPlanReminderNotification::TYPE_THREE_DAYS,
            countKey: 'three_days',
            sentAt: $now,
        );

        $this->processReminder(
            targetDate: $today,
            remindedColumn: 'reminded_due_at',
            reminderType: ReadingPlanReminderNotification::TYPE_DUE,
            countKey: 'due',
            sentAt: $now,
        );

        $this->processOverduePlans($today, $now);

        $this->components->info(sprintf(
            'Reading plans processed. expired=%d three_days=%d due=%d overdue=%d',
            $this->counts['expired'],
            $this->counts['three_days'],
            $this->counts['due'],
            $this->counts['overdue'],
        ));

        return self::SUCCESS;
    }

    /**
     * 指定日の進行中計画へリマインダーを送信し、送信日時を更新する。
     *
     * 計画を分割取得し、各計画をトランザクション内で行ロックして状態と送信済み日時を
     * 再確認することで、重複通知を防止する。
     *
     * @param  Carbon  $targetDate  通知対象とする目標読了日
     * @param  string  $remindedColumn  送信日時を記録するカラム名
     * @param  string  $reminderType  送信する通知種別
     * @param  string  $countKey  送信件数を加算する集計キー
     * @param  Carbon  $sentAt  送信日時として保存する時刻
     *
     * 戻り値はない。
     */
    private function processReminder(
        Carbon $targetDate,
        string $remindedColumn,
        string $reminderType,
        string $countKey,
        Carbon $sentAt,
    ): void {
        ReadingPlan::query()
            ->where('status', ReadingPlan::STATUS_IN_PROGRESS)
            ->whereDate('target_date', $targetDate->toDateString())
            ->whereNull($remindedColumn)
            ->orderBy('id')
            ->select('id')
            ->chunkById(100, function ($plans) use ($remindedColumn, $reminderType, $countKey, $sentAt): void {
                foreach ($plans as $plan) {
                    DB::transaction(function () use ($plan, $remindedColumn, $reminderType, $countKey, $sentAt): void {
                        $lockedPlan = ReadingPlan::query()
                            ->with('user')
                            ->whereKey($plan->id)
                            ->lockForUpdate()
                            ->first();

                        if (! $lockedPlan instanceof ReadingPlan
                            || $lockedPlan->status !== ReadingPlanStatus::IN_PROGRESS
                            || $lockedPlan->{$remindedColumn} !== null) {
                            return;
                        }

                        if (! $this->sendReminder($lockedPlan, $reminderType)) {
                            return;
                        }

                        $lockedPlan->forceFill([$remindedColumn => $sentAt])->save();
                        $this->counts[$countKey]++;
                    });
                }
            });
    }

    /**
     * 期限を過ぎた進行中計画へ未送信の通知を送り、失効状態へ遷移させる。
     *
     * 計画ごとにトランザクションと行ロックを使用し、通知日時、失効状態、
     * 失効日時を一括して保存することで重複処理を防止する。
     *
     * @param  Carbon  $today  期限超過を判定する基準日
     * @param  Carbon  $processedAt  通知日時と失効日時として保存する時刻
     *
     * 戻り値はない。
     */
    private function processOverduePlans(Carbon $today, Carbon $processedAt): void
    {
        ReadingPlan::query()
            ->where('status', ReadingPlan::STATUS_IN_PROGRESS)
            ->whereDate('target_date', '<', $today->toDateString())
            ->orderBy('id')
            ->select('id')
            ->chunkById(100, function ($plans) use ($processedAt): void {
                foreach ($plans as $plan) {
                    DB::transaction(function () use ($plan, $processedAt): void {
                        $lockedPlan = ReadingPlan::query()
                            ->with('user')
                            ->whereKey($plan->id)
                            ->lockForUpdate()
                            ->first();

                        if (! $lockedPlan instanceof ReadingPlan
                            || $lockedPlan->status !== ReadingPlanStatus::IN_PROGRESS) {
                            return;
                        }

                        if ($lockedPlan->reminded_overdue_at === null
                            && $this->sendReminder($lockedPlan, ReadingPlanReminderNotification::TYPE_OVERDUE)) {
                            $lockedPlan->reminded_overdue_at = $processedAt;
                            $this->counts['overdue']++;
                        }

                        $lockedPlan->status = ReadingPlanStatus::EXPIRED;
                        $lockedPlan->expired_at = $processedAt;
                        $lockedPlan->save();
                        $this->counts['expired']++;
                    });
                }
            });
    }

    /**
     * 読書計画の所有者へ指定種別のDatabase通知を同期送信する。
     *
     * @param  ReadingPlan  $readingPlan  通知対象の読書計画
     * @param  string  $reminderType  送信する通知種別
     * @return bool 通知レコードが新しく作成された場合はtrue
     */
    private function sendReminder(ReadingPlan $readingPlan, string $reminderType): bool
    {
        $beforeCount = $readingPlan->user
            ->notifications()
            ->where('type', ReadingPlanReminderNotification::class)
            ->count();

        Notification::sendNow(
            $readingPlan->user,
            new ReadingPlanReminderNotification($readingPlan, $reminderType),
        );

        return $readingPlan->user
            ->notifications()
            ->where('type', ReadingPlanReminderNotification::class)
            ->where('id', '!=', null)
            ->count() > $beforeCount;
    }
}
