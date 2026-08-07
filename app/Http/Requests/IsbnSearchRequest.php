<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IsbnSearchRequest extends FormRequest
{
    /**
     * ISBN検索を認証済みユーザーにのみ許可する。
     *
     * @return bool 認証済みの場合はtrue
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * 検証前にISBN入力の前後の空白を除去してリクエストへ再設定する。
     * 戻り値はない。
     */
    public function rules(): array
    {
        return [
            'isbn' => ['required', 'digits:13'],
        ];
    }

    public function messages(): array
    {
        return [
            'isbn.required' => 'ISBNは13桁で入力してください。',
            'isbn.digits' => 'ISBNは13桁で入力してください。',
        ];
    }
}
