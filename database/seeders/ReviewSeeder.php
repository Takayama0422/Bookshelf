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
            ['yamada@example.com', '9784101010014', 5, '語り口が軽妙で、何度読んでも新しい発見があります。'],
            ['suzuki@example.com', '9784101010014', 4, '猫の視点が面白く、時代を越えて楽しめました。'],
            ['tanaka@example.com', '9784101010014', 4, '風刺の鋭さとユーモアのバランスが好きです。'],
            ['sato@example.com', '9784422100524', 5, '人間関係を見直すきっかけになりました。'],
            ['takahashi@example.com', '9784422100524', 4, '実践しやすい助言が多く参考になります。'],
            ['yamada@example.com', '9784422100524', 3, '古典らしい読み応えがありました。'],
            ['suzuki@example.com', '9784873115658', 5, '実務で何度も読み返したい内容です。'],
            ['tanaka@example.com', '9784873115658', 5, 'コードを書く姿勢が変わる良書です。'],
            ['sato@example.com', '9784873115658', 4, '具体例が多く、チーム開発にも役立ちます。'],
            ['takahashi@example.com', '9784863940246', 4, '習慣化の重要性を改めて感じました。'],
            ['yamada@example.com', '9784863940246', 4, '仕事にも生活にも応用できる内容です。'],
            ['suzuki@example.com', '9784863940246', 3, 'じっくり読み返したい本でした。'],
            ['tanaka@example.com', '9784101010021', 5, '主人公のまっすぐさが気持ちよく読後感も爽快です。'],
            ['sato@example.com', '9784101010021', 5, 'テンポがよく、登場人物の癖も印象に残りました。'],
            ['takahashi@example.com', '9784101010021', 4, '古典としての魅力を感じました。'],
            ['yamada@example.com', '9784309226712', 4, '歴史を大きな流れで理解できました。'],
            ['suzuki@example.com', '9784309226712', 4, '視野が広がる読み応えのある一冊です。'],
            ['tanaka@example.com', '9784309226712', 3, '情報量が多く学びがありました。'],
            ['sato@example.com', '9784048930598', 5, '保守性を考えるうえで必読だと思います。'],
            ['takahashi@example.com', '9784048930598', 4, '具体的な改善例が参考になりました。'],
            ['yamada@example.com', '9784048930598', 4, 'チームで共有したい技術書です。'],
            ['suzuki@example.com', '9784478025819', 5, '対話形式で読みやすく考え方を整理できます。'],
            ['tanaka@example.com', '9784478025819', 5, '日常の悩みに引き寄せて読めました。'],
            ['sato@example.com', '9784478025819', 4, '何度も読み返したい内容でした。'],
            ['takahashi@example.com', '9784163902302', 4, '登場人物の関係性が印象的でした。'],
            ['yamada@example.com', '9784163902302', 4, '芸人の世界の厳しさが伝わります。'],
            ['suzuki@example.com', '9784163902302', 3, '余韻が残る作品でした。'],
            ['tanaka@example.com', '9784822289607', 5, 'データを見る姿勢が変わりました。'],
            ['sato@example.com', '9784822289607', 4, '思い込みを減らす考え方が学べます。'],
            ['takahashi@example.com', '9784822289607', 5, '家族にもすすめたい一冊です。'],
            ['yamada@example.com', '9784822251468', 4, '物流の見方が変わる興味深い本です。'],
            ['suzuki@example.com', '9784822251468', 4, '歴史とビジネスのつながりが面白いです。'],
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
