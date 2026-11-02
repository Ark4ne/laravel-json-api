<?php

declare(strict_types=1);

namespace Test\Unit\Filters;

use Ark4ne\JsonApi\Filters\Filters;
use Illuminate\Http\Resources\MissingValue;
use Test\Support\Reflect;
use Test\TestCase;

class FiltersTest extends TestCase
{
    public function test_adding_a_rule_returns_a_copy(): void
    {
        $filters = new Filters();

        $policy = $filters->can('view');
        $callback = $policy->when(static fn() => true);

        self::assertNotSame($filters, $policy);
        self::assertCount(0, self::rules($filters));
        self::assertCount(1, self::rules($policy));
        self::assertCount(2, self::rules($callback));
    }

    public function test_nothing_and_a_missing_value_are_left_as_they_are(): void
    {
        $filters = (new Filters())->when(static fn() => false);
        $missing = new MissingValue();

        self::assertNull($filters->apply(request(), null));
        self::assertSame($missing, $filters->apply(request(), $missing));
    }

    /**
     * @param Filters $filters
     *
     * @return array<mixed>
     */
    private static function rules(Filters $filters): array
    {
        $rules = Reflect::get($filters, 'rules');
        self::assertIsArray($rules);

        return $rules;
    }
}
