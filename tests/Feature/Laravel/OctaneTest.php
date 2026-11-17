<?php

declare(strict_types=1);

namespace Test\Feature\Laravel;

use Ark4ne\JsonApi\Laravel\JsonApiServiceProvider;
use Ark4ne\JsonApi\Support\Memo;
use Illuminate\Contracts\Events\Dispatcher;
use PHPUnit\Framework\Attributes\DataProvider;
use Test\Feature\FeatureTestCase;

/**
 * A long-running worker: nothing memoized outlives an operation of Octane.
 */
final class OctaneTest extends FeatureTestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function events(): iterable
    {
        foreach (JsonApiServiceProvider::OCTANE_TERMINATED as $event) {
            yield $event => [$event];
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        Memo::flush();
    }

    #[DataProvider('events')]
    public function test_the_memo_is_flushed_when_an_octane_operation_ends(string $event): void
    {
        $calls = 0;
        $compute = static function () use (&$calls): int {
            return ++$calls;
        };
        Memo::remember('test', 'key', $compute);

        $this->app?->make(Dispatcher::class)->dispatch($event);

        self::assertSame(2, Memo::remember('test', 'key', $compute));
    }
}
