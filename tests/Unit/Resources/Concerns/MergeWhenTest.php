<?php

declare(strict_types=1);

namespace Test\Unit\Resources\Concerns;

use Ark4ne\JsonApi\Descriptors\MergeWhenValue;
use Ark4ne\JsonApi\Exceptions\DefinitionException;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use ArrayIterator;
use Illuminate\Http\Resources\MergeValue;
use Illuminate\Support\Fluent;
use Test\Support\Reflect;
use Test\TestCase;

final class MergeWhenTest extends TestCase
{
    public function test_merge_when_marks_the_members_it_merges(): void
    {
        $merged = Reflect::invoke($this->resource(), 'mergeWhen', true, fn() => ['a' => 1]);

        self::assertInstanceOf(MergeWhenValue::class, $merged);
        self::assertSame(['a' => 1], $merged->data);
    }

    public function test_merge_when_marks_the_default_it_merges(): void
    {
        $merged = Reflect::invoke($this->resource(), 'mergeWhen', false, ['a' => 1], ['b' => 2]);

        self::assertInstanceOf(MergeWhenValue::class, $merged);
        self::assertSame(['b' => 2], $merged->data);
    }

    public function test_merge_when_merges_nothing_when_the_condition_fails_without_default(): void
    {
        $merged = Reflect::invoke($this->resource(), 'mergeWhen', false, ['a' => 1]);

        self::assertInstanceOf(MergeWhenValue::class, $merged);
        self::assertSame([], $merged->data);
    }

    public function test_merge_keeps_its_members_unmarked(): void
    {
        $merged = Reflect::invoke($this->resource(), 'merge', ['a' => 1]);

        self::assertInstanceOf(MergeValue::class, $merged);
        self::assertNotInstanceOf(MergeWhenValue::class, $merged);
        self::assertSame(['a' => 1], $merged->data);
    }

    public function test_merge_when_merges_nothing_from_null(): void
    {
        $merged = Reflect::invoke($this->resource(), 'mergeWhen', true, fn() => null);

        self::assertInstanceOf(MergeWhenValue::class, $merged);
        self::assertSame([], $merged->data);
    }

    public function test_merge_when_merges_an_arrayable(): void
    {
        $merged = Reflect::invoke($this->resource(), 'mergeWhen', true, new Fluent(['a' => 1]));

        self::assertInstanceOf(MergeWhenValue::class, $merged);
        self::assertSame(['a' => 1], $merged->data);
    }

    public function test_merge_unless_marks_the_members_it_merges(): void
    {
        self::assertInstanceOf(MergeWhenValue::class, Reflect::invoke($this->resource(), 'mergeUnless', false, ['a' => 1]));
    }

    public function test_merge_when_merges_an_iterable(): void
    {
        $merged = Reflect::invoke($this->resource(), 'mergeWhen', true, new ArrayIterator(['a' => 1]));

        self::assertInstanceOf(MergeWhenValue::class, $merged);
        self::assertSame(['a' => 1], $merged->data);
    }

    public function test_merge_when_refuses_members_that_cannot_be_merged(): void
    {
        $this->expectException(DefinitionException::class);
        $this->expectExceptionMessage('[int] given');

        Reflect::invoke($this->resource(), 'mergeWhen', true, 1);
    }

    /**
     * @return JsonApiResource<null>
     */
    private function resource(): JsonApiResource
    {
        return new class (null) extends JsonApiResource {};
    }
}
