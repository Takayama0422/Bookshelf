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
            ['taro.yamada@example.com', 'hanako.sato@example.com', '9784003101018'],
            ['taro.yamada@example.com', 'ichiro.suzuki@example.com', '9784003101018'],
            ['hanako.sato@example.com', 'taro.yamada@example.com', '9784003101018'],
            ['ichiro.suzuki@example.com', 'misaki.takahashi@example.com', '9784003101025'],
            ['misaki.takahashi@example.com', 'ken.tanaka@example.com', '9784003101025'],
            ['ken.tanaka@example.com', 'hanako.sato@example.com', '9784003101032'],
            ['taro.yamada@example.com', 'ichiro.suzuki@example.com', '9784003101032'],
            ['hanako.sato@example.com', 'misaki.takahashi@example.com', '9784003101032'],
            ['ichiro.suzuki@example.com', 'ken.tanaka@example.com', '9784003101049'],
            ['misaki.takahashi@example.com', 'taro.yamada@example.com', '9784003101049'],
            ['ken.tanaka@example.com', 'ichiro.suzuki@example.com', '9784003101056'],
            ['taro.yamada@example.com', 'misaki.takahashi@example.com', '9784003101056'],
            ['hanako.sato@example.com', 'ken.tanaka@example.com', '9784003101056'],
            ['ichiro.suzuki@example.com', 'taro.yamada@example.com', '9784003101063'],
            ['misaki.takahashi@example.com', 'hanako.sato@example.com', '9784003101063'],
            ['ken.tanaka@example.com', 'misaki.takahashi@example.com', '9784003101070'],
            ['taro.yamada@example.com', 'ken.tanaka@example.com', '9784003101070'],
            ['hanako.sato@example.com', 'ichiro.suzuki@example.com', '9784167110123'],
            ['ichiro.suzuki@example.com', 'hanako.sato@example.com', '9784167110123'],
            ['misaki.takahashi@example.com', 'ichiro.suzuki@example.com', '9784167110123'],
            ['ken.tanaka@example.com', 'taro.yamada@example.com', '9784062748681'],
            ['taro.yamada@example.com', 'misaki.takahashi@example.com', '9784873115658'],
            ['hanako.sato@example.com', 'ken.tanaka@example.com', '9784873115658'],
            ['ichiro.suzuki@example.com', 'taro.yamada@example.com', '9784478025819'],
        ];

        foreach ($likes as [$likerEmail, $reviewerEmail, $isbn]) {
            $liker = User::where('email', $likerEmail)->firstOrFail();
            $reviewer = User::where('email', $reviewerEmail)->firstOrFail();
            $book = Book::where('isbn', $isbn)->firstOrFail();
            $review = Review::where('user_id', $reviewer->id)
                ->where('book_id', $book->id)
                ->firstOrFail();

            $liker->likedReviews()->syncWithoutDetaching([
                $review->id => ['created_at' => $createdAt],
            ]);
        }
    }
}
