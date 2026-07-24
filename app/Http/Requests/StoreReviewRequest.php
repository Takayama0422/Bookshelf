<?php

namespace App\Http\Requests;

use App\Models\Book;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['required', 'string', 'max:1000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var Book $book */
                $book = $this->route('book');

                if ($this->user()?->reviews()->where('book_id', $book->id)->exists()) {
                    $validator->errors()->add('comment', 'この書籍には既にレビューを投稿しています。');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rating.required' => '評価は必ず選択してください。',
            'rating.integer' => '評価には整数を指定してください。',
            'rating.between' => '評価は1〜5の範囲で選択してください。',
            'comment.required' => 'コメントは必ず入力してください。',
            'comment.string' => 'コメントには文字列を指定してください。',
            'comment.max' => 'コメントは1000文字以内で入力してください。',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function reviewAttributes(): array
    {
        return $this->safe()->only(['rating', 'comment']);
    }
}
