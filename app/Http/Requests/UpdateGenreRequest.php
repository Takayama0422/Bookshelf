<?php

namespace App\Http\Requests;

use App\Models\Genre;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGenreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        /** @var Genre $genre */
        $genre = $this->route('genre');

        return [
            'name' => ['required', 'string', 'max:50', Rule::unique('genres', 'name')->ignore($genre)],
        ];
    }
}
