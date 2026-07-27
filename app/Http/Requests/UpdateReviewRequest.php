<?php

namespace App\Http\Requests;

use App\Models\Review;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Review $review */
        $review = $this->route('review');

        return $this->user()?->can('update', $review) ?? false;
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

    /**
     * 更新対象自身を除外してレビュー重複を検証する処理を返す。
     *
     * 同一ユーザー・書籍の別レビューが存在する場合はコメント項目へエラーを追加する。
     *
     * @return list<callable> 基本ルール適用後に実行する検証処理
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var Review $review */
                $review = $this->route('review');

                $duplicateExists = Review::query()
                    ->where('user_id', $review->user_id)
                    ->where('book_id', $review->book_id)
                    ->whereKeyNot($review->id)
                    ->exists();

                if ($duplicateExists) {
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
