<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\User;
use Illuminate\Database\Seeder;

class FavoriteSeeder extends Seeder
{
    public function run(): void
    {
        $createdAt = '2026-07-24 00:00:00';
        $favorites = [
            'taro.yamada@example.com' => ['9784003101032', '9784167110123', '9784873115658'],
            'hanako.sato@example.com' => ['9784003101018', '9784003101056', '9784478025819'],
            'ichiro.suzuki@example.com' => ['9784003101025', '9784003101070', '9784873115658'],
            'misaki.takahashi@example.com' => ['9784003101032', '9784062748681', '9784167110123'],
            'ken.tanaka@example.com' => ['9784003101056', '9784003101063', '9784478025819'],
        ];

        foreach ($favorites as $email => $isbns) {
            $user = User::where('email', $email)->firstOrFail();
            $bookIds = Book::whereIn('isbn', $isbns)->pluck('id');
            $user->favoriteBooks()->syncWithoutDetaching(
                $bookIds->mapWithKeys(fn (int $bookId): array => [$bookId => ['created_at' => $createdAt]])->all(),
            );
        }
    }
}
