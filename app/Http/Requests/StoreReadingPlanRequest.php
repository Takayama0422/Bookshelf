<?php

namespace App\Http\Requests;

use App\Models\ReadingPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReadingPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ReadingPlan::class) ?? false;
    }

    /**
     * 同一ユーザー・書籍に進行中の計画が重複しない読書計画登録ルールを返す。
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'book_id' => [
                'required',
                'integer',
                Rule::exists('books', 'id'),
                Rule::unique('reading_plans', 'book_id')
                    ->where('user_id', $this->user()?->id)
                    ->where('status', ReadingPlan::STATUS_IN_PROGRESS),
            ],
            'target_date' => ['required', 'date', 'after_or_equal:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'book_id.required' => '書籍を選択してください。',
            'book_id.exists' => '指定された書籍は存在しません。',
            'book_id.unique' => 'この書籍には進行中の読書計画がすでに登録されています。',
            'target_date.required' => '目標読了日を入力してください。',
            'target_date.date' => '目標読了日には有効な日付を指定してください。',
            'target_date.after_or_equal' => '目標読了日には本日以降の日付を指定してください。',
        ];
    }
}
