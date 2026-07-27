<?php

namespace App\Http\Requests;

use App\Rules\ValidIsbn;
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
    protected function prepareForValidation(): void
    {
        $this->merge(['isbn' => trim((string) $this->input('isbn', ''))]);
    }

    public function rules(): array
    {
        return [
            'isbn' => ['required', new ValidIsbn],
        ];
    }

    public function messages(): array
    {
        return [
            'isbn.required' => 'ISBNは正しいISBN-10またはISBN-13で入力してください。',
        ];
    }
}
