<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReadingPlan>
 */
class ReadingPlanFactory extends Factory
{
    protected $model = ReadingPlan::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'book_id' => Book::factory(),
            'target_date' => today()->addDays(7),
            'status' => 'in_progress',
            'completed_at' => null,
            'expired_at' => null,
            'reminded_three_days_at' => null,
            'reminded_due_at' => null,
            'reminded_overdue_at' => null,
        ];
    }
}
