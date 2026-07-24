<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Database\Seeder;

class BookSeeder extends Seeder
{
    public function run(): void
    {
        $books = [
            [
                'user' => 'taro.yamada@example.com',
                'title' => '吾輩は猫である',
                'author' => '夏目 漱石',
                'isbn' => '9784003101018',
                'published_date' => '1905-10-06',
                'description' => '猫の視点から人間社会を風刺的に描く近代文学作品。',
                'genres' => ['文学'],
            ],
            [
                'user' => 'hanako.sato@example.com',
                'title' => '坊っちゃん',
                'author' => '夏目 漱石',
                'isbn' => '9784003101025',
                'published_date' => '1906-04-01',
                'description' => '正義感の強い青年教師が地方の学校で奮闘する物語。',
                'genres' => ['文学'],
            ],
            [
                'user' => 'ichiro.suzuki@example.com',
                'title' => 'こころ',
                'author' => '夏目 漱石',
                'isbn' => '9784003101032',
                'published_date' => '1914-09-20',
                'description' => '先生と私の関係を通して近代的な自我と孤独を描く作品。',
                'genres' => ['文学'],
            ],
            [
                'user' => 'misaki.takahashi@example.com',
                'title' => '羅生門',
                'author' => '芥川 龍之介',
                'isbn' => '9784003101049',
                'published_date' => '1915-11-01',
                'description' => '極限状況に置かれた人間の倫理を描く短編小説。',
                'genres' => ['文学'],
            ],
            [
                'user' => 'ken.tanaka@example.com',
                'title' => '銀河鉄道の夜',
                'author' => '宮沢 賢治',
                'isbn' => '9784003101056',
                'published_date' => '1934-01-01',
                'description' => '幻想的な旅を通して生と死、幸福を問いかける物語。',
                'genres' => ['文学', 'ファンタジー'],
            ],
            [
                'user' => 'taro.yamada@example.com',
                'title' => '人間失格',
                'author' => '太宰 治',
                'isbn' => '9784003101063',
                'published_date' => '1948-07-25',
                'description' => '自己と社会のずれに苦しむ青年の告白体小説。',
                'genres' => ['文学'],
            ],
            [
                'user' => 'hanako.sato@example.com',
                'title' => '砂の女',
                'author' => '安部 公房',
                'isbn' => '9784003101070',
                'published_date' => '1962-06-01',
                'description' => '砂丘の穴に閉じ込められた男の日常を描く不条理小説。',
                'genres' => ['文学', 'SF'],
            ],
            [
                'user' => 'ichiro.suzuki@example.com',
                'title' => '容疑者Xの献身',
                'author' => '東野 圭吾',
                'isbn' => '9784167110123',
                'published_date' => '2005-08-25',
                'description' => '天才数学者の献身と推理が交錯するミステリー。',
                'genres' => ['ミステリー'],
            ],
            [
                'user' => 'misaki.takahashi@example.com',
                'title' => 'ノルウェイの森',
                'author' => '村上 春樹',
                'isbn' => '9784062748681',
                'published_date' => '1987-09-04',
                'description' => '喪失と再生を静かに描く長編小説。',
                'genres' => ['文学'],
            ],
            [
                'user' => 'ken.tanaka@example.com',
                'title' => 'リーダブルコード',
                'author' => 'Dustin Boswell, Trevor Foucher',
                'isbn' => '9784873115658',
                'published_date' => '2012-06-23',
                'description' => '読みやすいコードを書くための実践的な考え方をまとめた技術書。',
                'genres' => ['技術書'],
            ],
            [
                'user' => 'taro.yamada@example.com',
                'title' => '嫌われる勇気',
                'author' => '岸見 一郎, 古賀 史健',
                'isbn' => '9784478025819',
                'published_date' => '2013-12-13',
                'description' => 'アドラー心理学を対話形式で解説する自己啓発書。',
                'genres' => ['自己啓発', 'ビジネス'],
            ],
        ];

        foreach ($books as $bookData) {
            $user = User::where('email', $bookData['user'])->firstOrFail();
            $genreIds = Genre::whereIn('name', $bookData['genres'])->pluck('id');

            $book = Book::updateOrCreate(
                ['isbn' => $bookData['isbn']],
                [
                    'user_id' => $user->id,
                    'title' => $bookData['title'],
                    'author' => $bookData['author'],
                    'published_date' => $bookData['published_date'],
                    'description' => $bookData['description'],
                    'image_url' => null,
                ],
            );

            $book->genres()->sync($genreIds);
        }
    }
}
