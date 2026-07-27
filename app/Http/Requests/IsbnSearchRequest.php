<?php

namespace App\Http\Requests;

use App\Rules\ValidIsbn;
use Illuminate\Foundation\Http\FormRequest;

class IsbnSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

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
