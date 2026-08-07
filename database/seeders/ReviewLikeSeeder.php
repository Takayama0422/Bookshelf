<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReviewLikeSeeder extends Seeder
{
    public function run(): void
    {
        $createdAt = '2026-07-24 00:00:00';
        $likes = [
            ['yamada@example.com', 'suzuki@example.com', '9784101010014'],
            ['yamada@example.com', 'tanaka@example.com', '9784101010014'],
            ['suzuki@example.com', 'yamada@example.com', '9784101010014'],
            ['tanaka@example.com', 'sato@example.com', '9784422100524'],
            ['sato@example.com', 'takahashi@example.com', '9784422100524'],
            ['takahashi@example.com', 'suzuki@example.com', '9784873115658'],
            ['yamada@example.com', 'tanaka@example.com', '9784873115658'],
            ['suzuki@example.com', 'sato@example.com', '9784873115658'],
            ['tanaka@example.com', 'takahashi@example.com', '9784863940246'],
            ['sato@example.com', 'yamada@example.com', '9784863940246'],
            ['takahashi@example.com', 'tanaka@example.com', '9784101010021'],
            ['yamada@example.com', 'sato@example.com', '9784101010021'],
            ['suzuki@example.com', 'takahashi@example.com', '9784101010021'],
            ['tanaka@example.com', 'yamada@example.com', '9784309226712'],
            ['sato@example.com', 'suzuki@example.com', '9784309226712'],
            ['takahashi@example.com', 'sato@example.com', '9784048930598'],
            ['yamada@example.com', 'takahashi@example.com', '9784048930598'],
            ['suzuki@example.com', 'tanaka@example.com', '9784478025819'],
            ['tanaka@example.com', 'suzuki@example.com', '9784478025819'],
            ['sato@example.com', 'tanaka@example.com', '9784478025819'],
            ['takahashi@example.com', 'yamada@example.com', '9784163902302'],
            ['yamada@example.com', 'sato@example.com', '9784822289607'],
            ['suzuki@example.com', 'takahashi@example.com', '9784822289607'],
            ['tanaka@example.com', 'yamada@example.com', '9784822251468'],
        ];

        foreach ($likes as [$likerEmail, $reviewerEmail, $isbn]) {
            $liker = User::where('email', $likerEmail)->firstOrFail();
            $reviewer = User::where('email', $reviewerEmail)->firstOrFail();
            $book = Book::where('isbn', $isbn)->firstOrFail();
            $review = Review::where('user_id', $reviewer->id)
                ->where('book_id', $book->id)
                ->first();

            if ($review === null) {
                continue;
            }

            $liker->likedReviews()->syncWithoutDetaching([
                $review->id => ['created_at' => $createdAt],
            ]);
        }
    }
}
