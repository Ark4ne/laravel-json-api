<?php

declare(strict_types=1);

namespace Test\Unit\Rendering;

use Ark4ne\JsonApi\Descriptors\Values\ValueDate;
use Ark4ne\JsonApi\Descriptors\Values\ValueFloat;
use Ark4ne\JsonApi\Descriptors\Values\ValueInteger;
use Ark4ne\JsonApi\Descriptors\Values\ValueString;
use Ark4ne\JsonApi\Descriptors\Values\ValueStruct;
use Ark4ne\JsonApi\Rendering\ValueResolver;
use DateTimeImmutable;
use Illuminate\Http\Resources\MergeValue;
use Illuminate\Http\Resources\MissingValue;
use Illuminate\Support\Collection;
use LogicException;
use PHPUnit\Framework\TestCase;
use Test\Unit\Rendering\Fixtures\Contexts;

final class ValueResolverTest extends TestCase
{
    private const MODEL = ['name' => 'Ada', 'age' => '36', 'city' => 'London'];

    public function test_descriptors_closures_and_values_are_resolved(): void
    {
        $resolved = (new ValueResolver())->resolve([
            'name' => new ValueString(null),
            'age' => new ValueInteger('age'),
            'computed' => static fn() => 'value',
            'raw' => 'raw',
        ], self::MODEL, Contexts::of());

        self::assertSame(['name' => 'Ada', 'age' => 36, 'computed' => 'value', 'raw' => 'raw'], $resolved);
    }

    public function test_the_values_read_their_defaults_from_the_configuration_of_the_context(): void
    {
        $model = ['price' => 1.239, 'at' => new DateTimeImmutable('2024-05-01 10:00:00+00:00'), 'note' => null];
        $members = ['price' => new ValueFloat('price'), 'at' => new ValueDate('at'), 'note' => new ValueString('note')];

        $resolved = (new ValueResolver())->resolve($members, $model, Contexts::of(config: ['describer' => [
            'precision' => 1,
            'date' => 'Y-m-d',
            'nullable' => false,
        ]]));

        self::assertSame(['price' => 1.2, 'at' => '2024-05-01', 'note' => ''], $resolved);
        self::assertSame(
            ['price' => 1.239, 'at' => '2024-05-01T10:00:00+00:00', 'note' => null],
            (new ValueResolver())->resolve($members, $model, Contexts::of()),
        );
    }

    public function test_arrayable_values_are_rendered_as_plain_arrays(): void
    {
        $resolved = (new ValueResolver())->resolve([
            'list' => new Collection([1, 2]),
            'nested' => static fn() => ['inner' => new Collection(['a' => 'b'])],
        ], self::MODEL, Contexts::of());

        self::assertSame(['list' => [1, 2], 'nested' => ['inner' => ['a' => 'b']]], $resolved);
    }

    public function test_a_descriptor_without_key_is_named_after_what_it_reads(): void
    {
        self::assertSame(['city' => 'London'], (new ValueResolver())->resolve([new ValueString('city')], self::MODEL, Contexts::of()));
    }

    public function test_missing_values_and_failed_conditions_are_left_out(): void
    {
        $resolved = (new ValueResolver())->resolve([
            'missing' => new MissingValue(),
            'hidden' => (new ValueString('name'))->when(false),
            'shown' => (new ValueString('name'))->when(true),
            new MissingValue(),
        ], self::MODEL, Contexts::of());

        self::assertSame(['shown' => 'Ada'], $resolved);
    }

    public function test_the_members_of_apply_when_are_merged(): void
    {
        $resolved = (new ValueResolver())->resolve([
            'name' => new ValueString(null),
            new MergeValue(['age' => new ValueInteger(null), 'city' => static fn() => 'Paris']),
        ], self::MODEL, Contexts::of());

        self::assertSame(['name' => 'Ada', 'age' => 36, 'city' => 'Paris'], $resolved);
    }

    public function test_a_member_not_requested_is_never_resolved(): void
    {
        $resolved = (new ValueResolver())->resolve([
            'name' => new ValueString(null),
            'expensive' => static fn() => throw new LogicException('Never resolved.'),
            'descriptor' => new ValueString(static fn() => throw new LogicException('Never resolved.')),
        ], self::MODEL, Contexts::of('fields[user]=name'), 'user');

        self::assertSame(['name' => 'Ada'], $resolved);
    }

    public function test_the_fieldset_of_another_type_does_not_filter(): void
    {
        $resolved = (new ValueResolver())->resolve(
            ['name' => new ValueString(null)],
            self::MODEL,
            Contexts::of('fields[post]=title'),
            'user',
        );

        self::assertSame(['name' => 'Ada'], $resolved);
    }

    public function test_without_type_nothing_is_filtered(): void
    {
        $resolved = (new ValueResolver())->resolve(
            ['name' => new ValueString(null)],
            self::MODEL,
            Contexts::of('fields[user]=age'),
        );

        self::assertSame(['name' => 'Ada'], $resolved);
    }

    public function test_a_value_only_in_fields_is_resolved_when_explicitly_requested(): void
    {
        $members = [
            'name' => new ValueString(null),
            'bio' => (new ValueString(static fn() => 'long text'))->whenInFields(),
        ];

        self::assertSame(['name' => 'Ada'], (new ValueResolver())->resolve($members, self::MODEL, Contexts::of(), 'user'));
        self::assertSame(
            ['name' => 'Ada', 'bio' => 'long text'],
            (new ValueResolver())->resolve($members, self::MODEL, Contexts::of('fields[user]=name,bio'), 'user'),
        );
    }

    public function test_a_struct_is_filtered_member_by_member(): void
    {
        $members = [
            'address' => new ValueStruct(static fn() => [
                'city' => new ValueString('city'),
                'street' => static fn() => throw new LogicException('Never resolved.'),
                'geo' => new ValueStruct(static fn() => [
                    'lat' => static fn() => 1.5,
                    'lng' => static fn() => throw new LogicException('Never resolved.'),
                ]),
            ]),
        ];

        self::assertSame(
            ['address' => ['city' => 'London', 'geo' => ['lat' => 1.5]]],
            (new ValueResolver())->resolve($members, self::MODEL, Contexts::of('fields[user]=address.city,address.geo.lat'), 'user'),
        );
    }

    public function test_a_whole_struct_is_resolved_when_requested(): void
    {
        $members = [
            'address' => new ValueStruct(static fn() => [
                'city' => new ValueString('city'),
                new MergeValue(['zip' => static fn() => '75000']),
                'hidden' => (new ValueString('city'))->when(false),
            ]),
        ];

        self::assertSame(
            ['address' => ['city' => 'London', 'zip' => '75000']],
            (new ValueResolver())->resolve($members, self::MODEL, Contexts::of('fields[user]=address'), 'user'),
        );
    }

    public function test_a_struct_whose_condition_fails_is_left_out_without_reading_its_members(): void
    {
        $members = [
            'address' => (new ValueStruct(static fn() => throw new LogicException('Never resolved.')))->when(false),
        ];

        self::assertSame([], (new ValueResolver())->resolve($members, self::MODEL, Contexts::of()));
    }
}
