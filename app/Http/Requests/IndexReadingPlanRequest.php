<?php

namespace App\Http\Requests;

use App\Models\ReadingPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexReadingPlanRequest extends FormRequest
{
    /**
     * 認証ユーザーが読書計画一覧を表示できる場合のみ許可する。
     *
     * @return bool 一覧表示を許可する場合はtrue
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', ReadingPlan::class) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', Rule::in(ReadingPlan::statusValues())],
        ];
    }

    public function status(): ?string
    {
        return $this->validated('status');
    }
}
