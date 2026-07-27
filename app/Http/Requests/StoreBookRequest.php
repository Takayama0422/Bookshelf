<?php

namespace App\Http\Requests;

use App\Models\Book;
use Illuminate\Foundation\Http\FormRequest;

class StoreBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Book::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('genres') && $this->has('genre_ids')) {
            $this->merge(['genres' => $this->input('genre_ids')]);
        }
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'author' => ['required', 'string', 'max:255'],
            'isbn' => ['nullable', 'digits:13', 'unique:books,isbn'],
            'published_date' => ['nullable', 'date', 'before_or_equal:today'],
            'description' => ['nullable', 'string', 'max:2000'],
            'image_url' => ['nullable', 'url', 'max:2048'],
            'genres' => ['required', 'array', 'min:1'],
            'genres.*' => ['exists:genres,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'タイトルは必ず入力してください。',
            'title.string' => 'タイトルは文字列で入力してください。',
            'title.max' => 'タイトルは255文字以内で入力してください。',
            'author.required' => '著者は必ず入力してください。',
            'author.string' => '著者は文字列で入力してください。',
            'author.max' => '著者は255文字以内で入力してください。',
            'isbn.digits' => 'ISBNは13桁で入力してください。',
            'isbn.unique' => 'このISBNはすでに登録されています。',
            'published_date.date' => '出版日には有効な日付を指定してください。',
            'published_date.before_or_equal' => '出版日には本日以前の日付を指定してください。',
            'description.string' => '説明は文字列で入力してください。',
            'description.max' => '説明は2000文字以内で入力してください。',
            'image_url.url' => '画像URLには有効なURLを指定してください。',
            'image_url.max' => '画像URLは2048文字以内で入力してください。',
            'genres.required' => 'ジャンルは1つ以上選択してください。',
            'genres.array' => 'ジャンルは配列で指定してください。',
            'genres.min' => 'ジャンルは1つ以上選択してください。',
            'genres.*.exists' => '指定されたジャンルは存在しません。',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function bookAttributes(): array
    {
        return $this->safe()->only([
            'title',
            'author',
            'isbn',
            'published_date',
            'description',
            'image_url',
        ]);
    }

    /**
     * @return array<int, int|string>
     */
    public function genreIds(): array
    {
        return $this->validated('genres');
    }
}
