<?php

namespace Tests\Unit;

use App\Services\IsbnNormalizer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class IsbnNormalizerTest extends TestCase
{
    public function test_it_normalizes_spaces_tabs_hyphens_and_lowercase_isbn10_check_digit(): void
    {
        $this->assertSame('0306406152', IsbnNormalizer::normalize(" 0-306\t40615-2 "));
        $this->assertSame('080442957X', IsbnNormalizer::normalize('0-8044-2957-x'));
    }

    public function test_it_validates_isbn10_and_isbn13_check_digits(): void
    {
        $this->assertTrue(IsbnNormalizer::isValid('0306406152'));
        $this->assertTrue(IsbnNormalizer::isValid('9780306406157'));
        $this->assertFalse(IsbnNormalizer::isValid('0306406153'));
        $this->assertFalse(IsbnNormalizer::isValid('9780306406158'));
        $this->assertFalse(IsbnNormalizer::isValid('1234567890'));
    }

    public function test_it_rejects_invalid_isbn(): void
    {
        $this->expectException(InvalidArgumentException::class);

        IsbnNormalizer::normalize('not-an-isbn');
    }
}
