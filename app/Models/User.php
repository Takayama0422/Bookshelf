<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function books(): HasMany
    {
        return $this->hasMany(Book::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function readingPlans(): HasMany
    {
        return $this->hasMany(ReadingPlan::class);
    }

    public function favoriteBooks(): BelongsToMany
    {
        return $this->belongsToMany(Book::class, 'favorites')->withPivot('created_at');
    }

    /**
     * 指定書籍のお気に入り登録状態を切り替える。
     *
     * 登録済みの場合は中間テーブルから削除し、未登録の場合は登録日時とともに追加する。
     *
     * @param  Book  $book  登録状態を切り替える書籍
     */
    public function toggleFavorite(Book $book): void
    {
        if ($this->favoriteBooks()->whereKey($book->id)->exists()) {
            $this->favoriteBooks()->detach($book->id);

            return;
        }

        $this->favoriteBooks()->attach($book->id, [
            'created_at' => Carbon::now(),
        ]);
    }

    public function likedReviews(): BelongsToMany
    {
        return $this->belongsToMany(Review::class, 'review_likes')->withPivot('created_at');
    }

    /**
     * 指定レビューのいいね状態を切り替える。
     *
     * 登録済みの場合は中間テーブルから削除し、未登録の場合は登録日時とともに追加する。
     *
     * @param  Review  $review  いいね状態を切り替えるレビュー
     */
    public function toggleReviewLike(Review $review): void
    {
        if ($this->likedReviews()->whereKey($review->id)->exists()) {
            $this->likedReviews()->detach($review->id);

            return;
        }

        $this->likedReviews()->syncWithoutDetaching([
            $review->id => ['created_at' => Carbon::now()],
        ]);
    }
}
