<?php

declare(strict_types=1);

namespace Test\Unit\Rendering;

use Ark4ne\JsonApi\Rendering\ProjectedValues;
use Ark4ne\JsonApi\Rendering\ValueResolver;
use Illuminate\Http\Resources\MergeValue;
use Illuminate\Http\Resources\MissingValue;
use Illuminate\Support\Collection;
use LogicException;
use PHPUnit\Framework\TestCase;
use Test\Unit\Rendering\Fixtures\Contexts;

final class ValueResolverTest extends TestCase
{
    private const MODEL = ['name' => 'Ada', 'age' => '36', 'city' => 'London'];

    public function test_closures_and_values_are_resolved(): void
    {
        $resolved = (new ValueResolver())->resolve([
            'computed' => static fn() => 'value',
            'raw' => 'raw',
        ], self::MODEL, Contexts::of());

        self::assertSame(['computed' => 'value', 'raw' => 'raw'], $resolved);
    }

    public function test_arrayable_values_are_rendered_as_plain_arrays(): void
    {
        $resolved = (new ValueResolver())->resolve([
            'list' => new Collection([1, 2]),
            'nested' => static fn() => ['inner' => new Collection(['a' => 'b'])],
        ], self::MODEL, Contexts::of());

        self::assertSame(['list' => [1, 2], 'nested' => ['inner' => ['a' => 'b']]], $resolved);
    }

    public function test_missing_values_are_left_out(): void
    {
        $resolved = (new ValueResolver())->resolve([
            'missing' => new MissingValue(),
            'computed' => static fn() => new MissingValue(),
            'shown' => 'Ada',
            new MissingValue(),
        ], self::MODEL, Contexts::of());

        self::assertSame(['shown' => 'Ada'], $resolved);
    }

    public function test_the_members_of_a_merge_value_are_merged(): void
    {
        $resolved = (new ValueResolver())->resolve([
            'name' => 'Ada',
            new MergeValue(['age' => 36, 'city' => static fn() => 'London']),
        ], self::MODEL, Contexts::of());

        self::assertSame(['name' => 'Ada', 'age' => 36, 'city' => 'London'], $resolved);
    }

    public function test_a_member_not_requested_is_never_resolved(): void
    {
        $resolved = (new ValueResolver())->resolve([
            'name' => 'Ada',
            'age' => static fn() => throw new LogicException('Not requested.'),
        ], self::MODEL, Contexts::of('fields[user]=name'), 'user');

        self::assertSame(['name' => 'Ada'], $resolved);
    }

    public function test_the_fieldset_of_another_type_does_not_filter(): void
    {
        $resolved = (new ValueResolver())->resolve(
            ['name' => 'Ada', 'age' => 36],
            self::MODEL,
            Contexts::of('fields[post]=title'),
            'user',
        );

        self::assertSame(['name' => 'Ada', 'age' => 36], $resolved);
    }

    public function test_without_type_nothing_is_filtered(): void
    {
        $resolved = (new ValueResolver())->resolve(
            ['self' => 'https://api.example.com/users/1', 'docs' => 'https://example.com'],
            self::MODEL,
            Contexts::of('fields[user]=name'),
        );

        self::assertSame(['self' => 'https://api.example.com/users/1', 'docs' => 'https://example.com'], $resolved);
    }

    public function test_the_values_the_base_projected_are_given_as_they_are(): void
    {
        $values = ['name' => 'Ada', 'hidden' => fn(): string => 'kept: never resolved again'];
        $projected = new ProjectedValues($values);

        self::assertSame($values, (new ValueResolver())->resolve($projected, null, Contexts::of('fields[users]=name'), 'users'));
        self::assertSame($values, iterator_to_array($projected));
    }
}
