<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ReadingPlanSeeder extends Seeder
{
    /**
     * 応用機能の確認に必要な進行中・読了・失効済みの読書計画を登録する。
     *
     * 既存のユーザーと書籍を特定し、ユーザー・書籍の組み合わせごとに計画を更新または作成する。
     * 戻り値はない。
     */
    public function run(): void
    {
        $today = Carbon::today();
        $createdAt = $today->copy()->subDays(14)->startOfDay();
        $updatedAt = $today->copy()->subDays(7)->startOfDay();

        $plans = [
            ['yamada@example.com', '9784309226712', 3, 'in_progress', null, null],
            ['suzuki@example.com', '9784822251468', 14, 'in_progress', null, null],
            ['tanaka@example.com', '9784873115658', -7, 'completed', -2, null],
            ['sato@example.com', '9784101010014', -3, 'completed', -1, null],
            ['takahashi@example.com', '9784478025819', -5, 'expired', null, 0],
            ['yamada@example.com', '9784163902302', -10, 'expired', null, -4],
        ];

        foreach ($plans as [$email, $isbn, $targetOffset, $status, $completedOffset, $expiredOffset]) {
            $user = User::where('email', $email)->firstOrFail();
            $book = Book::where('isbn', $isbn)->firstOrFail();

            ReadingPlan::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'book_id' => $book->id,
                ],
                [
                    'target_date' => $today->copy()->addDays($targetOffset)->toDateString(),
                    'status' => $status,
                    'completed_at' => $completedOffset === null
                        ? null
                        : $today->copy()->addDays($completedOffset)->startOfDay(),
                    'expired_at' => $expiredOffset === null
                        ? null
                        : $today->copy()->addDays($expiredOffset)->startOfDay(),
                    'reminded_three_days_at' => null,
                    'reminded_due_at' => null,
                    'reminded_overdue_at' => null,
                    'created_at' => $createdAt,
                    'updated_at' => $updatedAt,
                ],
            );
        }
    }
}
