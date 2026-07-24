<?php

namespace Database\Seeders;

use App\Models\Genre;
use Illuminate\Database\Seeder;

class GenreSeeder extends Seeder
{
    public function run(): void
    {
        $genres = [
            '文学',
            'ミステリー',
            'SF',
            'ファンタジー',
            'ビジネス',
            '技術書',
            '歴史',
            '自己啓発',
            'エッセイ',
            '漫画',
        ];

        foreach ($genres as $genre) {
            Genre::updateOrCreate(['name' => $genre]);
        }
    }
}
