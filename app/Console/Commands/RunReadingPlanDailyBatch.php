<?php

namespace App\Console\Commands;

use App\Enums\ReadingPlanStatus;
use App\Models\ReadingPlan;
use App\Notifications\PlanReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class RunReadingPlanDailyBatch extends Command
{
    protected $signature = 'reading-plans:run-daily';

    protected $description = '読書計画の期限更新とリマインダー通知を実行します。';

    /**
     * 期限切れ化と3種類の読書計画リマインダーを一度だけ処理する。
     *
     * @return int コマンドの終了コード
     */
    public function handle(): int
    {
        $today = Carbon::today();
        $now = Carbon::now();

        $expiredCount = ReadingPlan::query()
            ->active()
            ->whereDate('target_date', '<', $today)
            ->update([
                'status' => ReadingPlanStatus::Expired,
                'expired_at' => $now,
            ]);

        $threeDaysCount = $this->processReminders(
            $today->copy()->addDays(3),
            'reminded_three_days_at',
            PlanReminderNotification::TIMING_THREE_DAYS_BEFORE,
        );
        $dueCount = $this->processReminders(
            $today,
            'reminded_due_at',
            PlanReminderNotification::TIMING_DUE_TODAY,
        );
        $threeDaysAfterCount = $this->processReminders(
            $today->copy()->subDays(3),
            'reminded_overdue_at',
            PlanReminderNotification::TIMING_THREE_DAYS_AFTER,
            expired: true,
        );

        $this->components->info(sprintf(
            'Reading plans processed. expired=%d three_days_before=%d due_today=%d three_days_after=%d',
            $expiredCount,
            $threeDaysCount,
            $dueCount,
            $threeDaysAfterCount,
        ));

        return self::SUCCESS;
    }

    /**
     * 指定日・状態の計画へ通知を送り、通知済み日時を保存する。
     *
     * @param  Carbon  $targetDate  通知対象日
     * @param  string  $remindedColumn  重複防止に使う日時カラム
     * @param  string  $timing  通知タイミング
     * @param  bool  $expired  期限切れ計画を対象にするか
     * @return int 新規通知数
     */
    private function processReminders(
        Carbon $targetDate,
        string $remindedColumn,
        string $timing,
        bool $expired = false,
    ): int {
        $count = 0;
        $plans = ReadingPlan::query()
            ->with(['user', 'book'])
            ->when($expired, fn ($query) => $query->expired(), fn ($query) => $query->active())
            ->whereDate('target_date', $targetDate)
            ->whereNull($remindedColumn)
            ->get();

        foreach ($plans as $plan) {
            $sent = DB::transaction(function () use ($plan, $remindedColumn, $timing): bool {
                $lockedPlan = ReadingPlan::query()
                    ->with(['user', 'book'])
                    ->whereKey($plan->id)
                    ->lockForUpdate()
                    ->first();

                if (! $lockedPlan || $lockedPlan->{$remindedColumn} !== null) {
                    return false;
                }

                $before = $lockedPlan->user->notifications()
                    ->where('type', PlanReminderNotification::class)
                    ->count();

                Notification::sendNow(
                    $lockedPlan->user,
                    new PlanReminderNotification($lockedPlan, $timing),
                );

                $after = $lockedPlan->user->notifications()
                    ->where('type', PlanReminderNotification::class)
                    ->count();
                if ($after === $before) {
                    return false;
                }

                $lockedPlan->forceFill([$remindedColumn => Carbon::now()])->save();

                return true;
            });

            if ($sent) {
                $count++;
            }
        }

        return $count;
    }
}
