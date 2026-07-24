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
            'isbn' => ['required', 'digits:13', 'unique:books,isbn'],
            'published_date' => ['required', 'date', 'before_or_equal:today'],
            'description' => ['nullable', 'string', 'max:2000'],
            'image_url' => ['nullable', 'url', 'max:2048'],
            'genres' => ['required', 'array', 'min:1'],
            'genres.*' => ['exists:genres,id'],
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
