<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class Favorite extends Pivot
{
    public $incrementing = true;

    public $timestamps = false;

    protected $table = 'favorites';

    protected $fillable = [
        'user_id',
        'book_id',
        'created_at',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}
