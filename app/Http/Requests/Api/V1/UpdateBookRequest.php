<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Book;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        $book = $this->route('book');

        return $book instanceof Book && $this->user()?->can('update', $book);
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('genre_ids') && $this->has('genres')) {
            $this->merge(['genre_ids' => $this->input('genres')]);
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        /** @var Book $book */
        $book = $this->route('book');

        return [
            'title' => ['required', 'string', 'max:255'],
            'author' => ['required', 'string', 'max:255'],
            'isbn' => ['nullable', 'digits:13', Rule::unique('books', 'isbn')->ignore($book)],
            'published_date' => ['nullable', 'date', 'before_or_equal:today'],
            'description' => ['nullable', 'string', 'max:2000'],
            'image_url' => ['nullable', 'url', 'max:2048'],
            'genre_ids' => ['required', 'array', 'min:1'],
            'genre_ids.*' => ['integer', 'exists:genres,id'],
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
            'genre_ids.required' => 'ジャンルは1つ以上指定してください。',
            'genre_ids.array' => 'ジャンルは配列で指定してください。',
            'genre_ids.min' => 'ジャンルは1つ以上指定してください。',
            'genre_ids.*.integer' => 'ジャンルIDは整数で入力してください。',
            'genre_ids.*.exists' => '指定されたジャンルは存在しません。',
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
        return $this->validated('genre_ids');
    }
}
