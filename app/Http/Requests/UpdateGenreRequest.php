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
            'name' => ['required', 'string', 'max:255', Rule::unique('genres', 'name')->ignore($genre)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'ジャンル名は必須です。',
            'name.string' => 'ジャンル名は文字列で入力してください。',
            'name.max' => 'ジャンル名は255文字以内で入力してください。',
            'name.unique' => 'そのジャンル名は既に使用されています。',
        ];
    }
}
