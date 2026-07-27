<?php

namespace App\Http\Requests;

use App\Models\ReadingPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReadingPlanRequest extends FormRequest
{
    /**
     * ルートの読書計画が存在し、認証ユーザーが所有者である場合のみ更新を許可する。
     *
     * @return bool 更新を許可する場合はtrue
     */
    public function authorize(): bool
    {
        $readingPlan = $this->route('reading_plan');

        return $readingPlan instanceof ReadingPlan
            && ($this->user()?->can('update', $readingPlan) ?? false);
    }

    /**
     * 読書計画の更新ルールを返す。
     *
     * 対象が進行中の場合は、更新対象自身を除外して同一ユーザー・書籍の重複を検証する。
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $readingPlan = $this->route('reading_plan');
        $bookRules = [
            'required',
            'integer',
            Rule::exists('books', 'id'),
        ];

        if ($readingPlan instanceof ReadingPlan && $readingPlan->status === ReadingPlan::STATUS_IN_PROGRESS) {
            $bookRules[] = Rule::unique('reading_plans', 'book_id')
                ->where('user_id', $this->user()?->id)
                ->where('status', ReadingPlan::STATUS_IN_PROGRESS)
                ->ignore($readingPlan->id);
        }

        return [
            'book_id' => $bookRules,
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

    /**
     * @return array<string, mixed>
     */
    public function readingPlanAttributes(): array
    {
        return $this->safe()->only(['book_id', 'target_date']);
    }
}
