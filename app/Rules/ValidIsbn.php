<?php

namespace App\Rules;

use App\Services\IsbnNormalizer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidIsbn implements ValidationRule
{
    /**
     * ISBN-10またはISBN-13として無効な値をバリデーションエラーにする。
     *
     * @param  string  $attribute  検証対象の属性名
     * @param  mixed  $value  検証する入力値
     * @param  Closure  $fail  エラーメッセージを登録するコールバック
     *
     * 戻り値はない。
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! IsbnNormalizer::isValid($value)) {
            $fail('ISBNは正しいISBN-10またはISBN-13で入力してください。');
        }
    }
}
