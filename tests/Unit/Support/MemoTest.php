<?php

declare(strict_types=1);

namespace Test\Unit\Support;

use Ark4ne\JsonApi\Support\Memo;
use PHPUnit\Framework\TestCase;

final class MemoTest extends TestCase
{
    protected function tearDown(): void
    {
        Memo::flush();
    }

    public function test_a_value_is_computed_once_per_scope_and_key(): void
    {
        $calls = 0;
        $compute = static function () use (&$calls): int {
            return ++$calls;
        };

        self::assertSame(1, Memo::remember('scope', 'key', $compute));
        self::assertSame(1, Memo::remember('scope', 'key', $compute));
        self::assertSame(2, Memo::remember('scope', 'other', $compute));
        self::assertSame(3, Memo::remember('other', 'key', $compute));
        self::assertSame(3, $calls);
    }

    public function test_a_null_value_is_remembered(): void
    {
        $calls = 0;
        $compute = static function () use (&$calls): null {
            $calls++;

            return null;
        };

        Memo::remember('scope', 'key', $compute);
        Memo::remember('scope', 'key', $compute);

        self::assertSame(1, $calls);
    }

    public function test_flush_forgets_every_value(): void
    {
        $calls = 0;
        $compute = static function () use (&$calls): int {
            return ++$calls;
        };

        Memo::remember('scope', 'key', $compute);
        Memo::flush();

        self::assertSame(2, Memo::remember('scope', 'key', $compute));
    }

    public function test_flush_forgets_the_values_of_one_scope(): void
    {
        $calls = 0;
        $compute = static function () use (&$calls): int {
            return ++$calls;
        };

        Memo::remember('a', 'key', $compute);
        Memo::remember('b', 'key', $compute);
        Memo::flush('a');

        self::assertSame(3, Memo::remember('a', 'key', $compute));
        self::assertSame(2, Memo::remember('b', 'key', $compute));
    }
}
