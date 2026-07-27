<?php

namespace App\Models;

use App\Enums\ReadingPlanStatus;
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
            ReadingPlanStatus::IN_PROGRESS->value => ReadingPlanStatus::IN_PROGRESS->label(),
            ReadingPlanStatus::COMPLETED->value => ReadingPlanStatus::COMPLETED->label(),
            ReadingPlanStatus::EXPIRED->value => ReadingPlanStatus::EXPIRED->label(),
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
}
