<?php

namespace App\Models;

use App\Enums\ReadingPlanStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReadingPlan extends Model
{
    use HasFactory;

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'user_id',
        'book_id',
        'target_date',
        'status',
        'completed_at',
        'expired_at',
        'reminded_three_days_at',
        'reminded_due_at',
        'reminded_overdue_at',
    ];

    protected $casts = [
        'status' => ReadingPlanStatus::class,
        'target_date' => 'date',
        'completed_at' => 'datetime',
        'expired_at' => 'datetime',
        'reminded_three_days_at' => 'datetime',
        'reminded_due_at' => 'datetime',
        'reminded_overdue_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    /**
     * @return array<string, string>
     */
    public static function statuses(): array
    {
        return [
            ReadingPlanStatus::InProgress->value => ReadingPlanStatus::InProgress->label(),
            ReadingPlanStatus::Completed->value => ReadingPlanStatus::Completed->label(),
            ReadingPlanStatus::Expired->value => ReadingPlanStatus::Expired->label(),
        ];
    }

    /**
     * @return list<string>
     */
    public static function statusValues(): array
    {
        return array_map(static fn (ReadingPlanStatus $status): string => $status->value, ReadingPlanStatus::cases());
    }

    public function statusLabel(): string
    {
        return $this->status instanceof ReadingPlanStatus
            ? $this->status->label()
            : (string) $this->status;
    }

    /**
     * 進行中の読書計画だけを取得する。
     *
     * @param  Builder<ReadingPlan>  $query  読書計画クエリ
     * @return Builder<ReadingPlan> 絞り込み済みクエリ
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ReadingPlanStatus::InProgress);
    }

    /**
     * 完了済みの読書計画だけを取得する。
     *
     * @param  Builder<ReadingPlan>  $query  読書計画クエリ
     * @return Builder<ReadingPlan> 絞り込み済みクエリ
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', ReadingPlanStatus::Completed);
    }

    /**
     * 期限切れの読書計画だけを取得する。
     *
     * @param  Builder<ReadingPlan>  $query  読書計画クエリ
     * @return Builder<ReadingPlan> 絞り込み済みクエリ
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('status', ReadingPlanStatus::Expired);
    }
}
