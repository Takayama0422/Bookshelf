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
