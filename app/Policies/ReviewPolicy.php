<?php

namespace App\Policies;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;

class ReviewPolicy
{
    /**
     * 同一ユーザーが同一書籍へ未投稿の場合のみレビュー作成を許可する。
     *
     * @param  User  $user  レビュー投稿を試みるユーザー
     * @param  Book  $book  レビュー対象の書籍
     * @return bool レビューを作成できる場合はtrue
     */
    public function create(User $user, Book $book): bool
    {
        return ! $user->reviews()
            ->where('book_id', $book->id)
            ->exists();
    }

    public function update(User $user, Review $review): bool
    {
        return $user->id === $review->user_id;
    }

    public function delete(User $user, Review $review): bool
    {
        return $user->id === $review->user_id;
    }
}
