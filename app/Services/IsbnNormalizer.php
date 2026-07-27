<?php

namespace App\Services;

use InvalidArgumentException;

class IsbnNormalizer
{
    public static function normalize(string $isbn): string
    {
        $normalized = self::sanitize($isbn);

        if (! self::isValid($normalized)) {
            throw new InvalidArgumentException('Invalid ISBN.');
        }

        return $normalized;
    }

    public static function sanitize(string $isbn): string
    {
        return strtoupper(preg_replace('/[ \t-]/', '', trim($isbn)) ?? '');
    }

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
