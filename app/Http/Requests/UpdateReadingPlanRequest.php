<?php

namespace App\Http\Requests;

use App\Models\ReadingPlan;
use Illuminate\Foundation\Http\FormRequest;

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
     * 編集対象は期日だけに限定する。
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'target_date' => ['required', 'date', 'after_or_equal:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
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
        return $this->safe()->only(['target_date']);
    }
}
