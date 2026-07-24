<?php

namespace App\Http\Requests;

use App\Models\Book;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Book $book */
        $book = $this->route('book');

        return $this->user()?->can('update', $book) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('genres') && $this->has('genre_ids')) {
            $this->merge(['genres' => $this->input('genre_ids')]);
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
            'isbn' => ['required', 'digits:13', Rule::unique('books', 'isbn')->ignore($book)],
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
