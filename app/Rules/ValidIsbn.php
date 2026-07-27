<?php

namespace App\Rules;

use App\Services\IsbnNormalizer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidIsbn implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! IsbnNormalizer::isValid($value)) {
            $fail('ISBNは正しいISBN-10またはISBN-13で入力してください。');
        }
    }
}
