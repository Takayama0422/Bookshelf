<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IssueTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'token_name' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'メールアドレスは必ず入力してください。',
            'email.email' => 'メールアドレスには有効な形式を指定してください。',
            'password.required' => 'パスワードは必ず入力してください。',
            'password.string' => 'パスワードは文字列で入力してください。',
            'token_name.required' => 'トークン名は必ず入力してください。',
            'token_name.string' => 'トークン名は文字列で入力してください。',
            'token_name.max' => 'トークン名は255文字以内で入力してください。',
        ];
    }
}
