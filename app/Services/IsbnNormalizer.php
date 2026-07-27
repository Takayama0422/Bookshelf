<?php

namespace App\Services;

use InvalidArgumentException;

class IsbnNormalizer
{
    /**
     * ISBNから区切り文字を除去して大文字化し、チェックディジットを検証する。
     *
     * @param  string  $isbn  正規化するISBN
     * @return string 検証済みのISBN
     *
     * @throws InvalidArgumentException ISBNが不正な場合
     */
    public static function normalize(string $isbn): string
    {
        $normalized = self::sanitize($isbn);

        if (! self::isValid($normalized)) {
            throw new InvalidArgumentException('Invalid ISBN.');
        }

        return $normalized;
    }

    /**
     * ISBNの前後空白、空白、タブ、ハイフンを除去して大文字化する。
     *
     * @param  string  $isbn  整形するISBN
     * @return string 整形後のISBN
     */
    public static function sanitize(string $isbn): string
    {
        return strtoupper(preg_replace('/[ \t-]/', '', trim($isbn)) ?? '');
    }

    /**
     * ISBN-10またはISBN-13の形式とチェックディジットを検証する。
     *
     * @param  string  $isbn  検証するISBN
     * @return bool 有効なISBNの場合はtrue
     */
    public static function isValid(string $isbn): bool
    {
        $normalized = self::sanitize($isbn);

        if (preg_match('/^\d{9}[\dX]$/', $normalized) === 1) {
            $sum = 0;
            for ($index = 0; $index < 10; $index++) {
                $value = $normalized[$index] === 'X' ? 10 : (int) $normalized[$index];
                $sum += $value * (10 - $index);
            }

            return $sum % 11 === 0;
        }

        if (preg_match('/^\d{13}$/', $normalized) !== 1) {
            return false;
        }

        $sum = 0;
        for ($index = 0; $index < 12; $index++) {
            $sum += (int) $normalized[$index] * ($index % 2 === 0 ? 1 : 3);
        }

        return (10 - ($sum % 10)) % 10 === (int) $normalized[12];
    }
}
