<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReviewSeeder extends Seeder
{
    public function run(): void
    {
        $reviews = [
            ['taro.yamada@example.com', '9784003101018', 5, '語り口が軽妙で、何度読んでも新しい発見があります。'],
            ['hanako.sato@example.com', '9784003101018', 4, '猫の視点が面白く、時代を越えて楽しめました。'],
            ['ichiro.suzuki@example.com', '9784003101018', 4, '風刺の鋭さとユーモアのバランスが好きです。'],
            ['misaki.takahashi@example.com', '9784003101025', 5, '主人公のまっすぐさが気持ちよく読後感も爽快です。'],
            ['ken.tanaka@example.com', '9784003101025', 4, 'テンポがよく、登場人物の癖も印象に残りました。'],
            ['taro.yamada@example.com', '9784003101025', 3, '古典らしい読み応えがありました。'],
            ['hanako.sato@example.com', '9784003101032', 5, '静かな文章の中に強い緊張感があります。'],
            ['ichiro.suzuki@example.com', '9784003101032', 5, '人間関係の揺らぎが丁寧に描かれていました。'],
            ['misaki.takahashi@example.com', '9784003101032', 4, '余韻が長く残る作品でした。'],
            ['ken.tanaka@example.com', '9784003101049', 4, '短編ながら場面の迫力が強烈でした。'],
            ['taro.yamada@example.com', '9784003101049', 4, '人間の弱さを鋭く描いています。'],
            ['hanako.sato@example.com', '9784003101049', 3, '読みやすく、考えさせられる内容でした。'],
            ['ichiro.suzuki@example.com', '9784003101056', 5, '幻想的な世界観がとても美しいです。'],
            ['misaki.takahashi@example.com', '9784003101056', 5, '優しさと寂しさが同居した名作だと思います。'],
            ['ken.tanaka@example.com', '9784003101056', 4, '物語の象徴性が印象深かったです。'],
            ['taro.yamada@example.com', '9784003101063', 4, '痛みを伴う読書体験でしたが心に残りました。'],
            ['hanako.sato@example.com', '9784003101063', 4, '語りの切実さに引き込まれました。'],
            ['ichiro.suzuki@example.com', '9784003101063', 3, '重いテーマですが読む価値があります。'],
            ['misaki.takahashi@example.com', '9784003101070', 5, '不条理な設定なのに現実味がありました。'],
            ['ken.tanaka@example.com', '9784003101070', 4, '閉塞感の描写が巧みです。'],
            ['taro.yamada@example.com', '9784003101070', 4, '独特な読後感がありました。'],
            ['hanako.sato@example.com', '9784167110123', 5, 'ミステリーとしても人間ドラマとしても見事です。'],
            ['ichiro.suzuki@example.com', '9784167110123', 5, '伏線の回収が鮮やかで一気に読みました。'],
            ['misaki.takahashi@example.com', '9784167110123', 4, '切なさの残る結末が印象的でした。'],
            ['ken.tanaka@example.com', '9784062748681', 4, '静かな文章で喪失感が丁寧に描かれています。'],
            ['taro.yamada@example.com', '9784062748681', 4, '世界観に浸れる作品でした。'],
            ['hanako.sato@example.com', '9784062748681', 3, '好みは分かれそうですが印象に残ります。'],
            ['ichiro.suzuki@example.com', '9784873115658', 5, '実務で何度も読み返したい内容です。'],
            ['misaki.takahashi@example.com', '9784873115658', 4, '具体例が多く、チーム開発にも役立ちます。'],
            ['ken.tanaka@example.com', '9784873115658', 5, 'コードを書く姿勢が変わる良書です。'],
            ['taro.yamada@example.com', '9784478025819', 4, '対話形式で読みやすく考え方を整理できます。'],
            ['hanako.sato@example.com', '9784478025819', 4, '日常の悩みに引き寄せて読めました。'],
        ];

        foreach ($reviews as [$email, $isbn, $rating, $comment]) {
            $user = User::where('email', $email)->firstOrFail();
            $book = Book::where('isbn', $isbn)->firstOrFail();

            Review::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'book_id' => $book->id,
                ],
                [
                    'rating' => $rating,
                    'comment' => $comment,
                ],
            );
        }
    }
}
