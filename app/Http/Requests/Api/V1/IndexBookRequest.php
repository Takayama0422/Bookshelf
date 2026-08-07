<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class IndexBookRequest extends FormRequest
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
            'keyword' => ['nullable', 'string', 'max:255'],
            'genre_id' => ['nullable', 'integer', 'exists:genres,id'],
            'sort' => ['nullable', 'string', 'in:latest,oldest,title,rating'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('genre_id') && $this->has('genre')) {
            $this->merge(['genre_id' => $this->input('genre')]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'keyword.string' => 'キーワードは文字列で入力してください。',
            'keyword.max' => 'キーワードは255文字以内で入力してください。',
            'genre_id.integer' => 'ジャンルIDは整数で入力してください。',
            'genre_id.exists' => '指定されたジャンルは存在しません。',
            'sort.in' => '並び順はlatest、oldest、title、ratingのいずれかを指定してください。',
            'page.integer' => 'ページ番号は整数で入力してください。',
            'page.min' => 'ページ番号は1以上で入力してください。',
            'per_page.integer' => '1ページあたりの件数は整数で入力してください。',
            'per_page.min' => '1ページあたりの件数は1以上で入力してください。',
            'per_page.max' => '1ページあたりの件数は100以下で入力してください。',
        ];
    }

    public function keyword(): ?string
    {
        return $this->validated('keyword');
    }

    public function genreId(): ?int
    {
        $genre = $this->validated('genre_id');

        return $genre === null ? null : (int) $genre;
    }

    public function sort(): string
    {
        return $this->validated('sort', 'latest');
    }

    public function perPage(): int
    {
        return (int) $this->validated('per_page', 20);
    }
}
