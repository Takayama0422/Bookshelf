<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ReviewLike extends Pivot
{
    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'review_likes';

    protected $fillable = [
        'user_id',
        'review_id',
        'created_at',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }
}
