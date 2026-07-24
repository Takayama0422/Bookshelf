<?php

namespace App\Policies;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;

class ReviewPolicy
{
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
