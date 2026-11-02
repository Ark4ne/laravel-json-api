<?php

declare(strict_types=1);

namespace Test\Unit\Descriptors;

use Ark4ne\JsonApi\Descriptors\Relations;
use Ark4ne\JsonApi\Descriptors\Relations\RelationMany;
use Ark4ne\JsonApi\Descriptors\Relations\RelationOne;
use Ark4ne\JsonApi\Descriptors\Values;
use Ark4ne\JsonApi\Descriptors\Values\ValueArray;
use Ark4ne\JsonApi\Descriptors\Values\ValueBool;
use Ark4ne\JsonApi\Descriptors\Values\ValueDate;
use Ark4ne\JsonApi\Descriptors\Values\ValueDateTime;
use Ark4ne\JsonApi\Descriptors\Values\ValueEnum;
use Ark4ne\JsonApi\Descriptors\Values\ValueFloat;
use Ark4ne\JsonApi\Descriptors\Values\ValueInteger;
use Ark4ne\JsonApi\Descriptors\Values\ValueMixed;
use Ark4ne\JsonApi\Descriptors\Values\ValueString;
use Ark4ne\JsonApi\Resources\JsonApiCollection;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Closure;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use stdClass;
use Test\Support\Reflect;
use Test\TestCase;

class DescriptorsTraitTest extends TestCase
{
    public static function methods()
    {
        return [
            'bool' => [ValueBool::class, 'bool'],
            'integer' => [ValueInteger::class, 'integer'],
            'float' => [ValueFloat::class, 'float'],
            'string' => [ValueString::class, 'string'],
            'date' => [ValueDate::class, 'date'],
            'datetime' => [ValueDateTime::class, 'datetime'],
            'array' => [ValueArray::class, 'array'],
            'enum' => [ValueEnum::class, 'enum'],
            'mixed' => [ValueMixed::class, 'mixed'],
            'one' => [RelationOne::class, 'one', JsonApiResource::class],
            'many' => [RelationMany::class, 'many', JsonApiCollection::class],
        ];
    }

    /**
     * @dataProvider methods
     */
    #[DataProvider('methods')]
    public function test_each_helper_builds_its_descriptor_with_its_retriever(mixed $expected, mixed $method, mixed ...$args): void
    {
        $mock = new class extends stdClass {
            /** @use Values<mixed> */ use Values;
            /** @use Relations<mixed> */ use Relations;
        };
        /** @var \Ark4ne\JsonApi\Descriptors\Describer $descriptor */
        $descriptor = Reflect::invoke($mock, $method, ...$args);
        self::assertInstanceOf($expected, $descriptor);
        self::assertNull($descriptor->retriever());
        $descriptor = Reflect::invoke($mock, $method, ...[...$args, 'test']);
        self::assertInstanceOf($expected, $descriptor);
        self::assertEquals('test', $descriptor->retriever());
        $descriptor = Reflect::invoke($mock, $method, ...[...$args, fn() => 'closure']);
        self::assertInstanceOf($expected, $descriptor);
        self::assertInstanceOf(Closure::class, $descriptor->retriever());
    }

    #[DataProvider('methods')]
    public function test_every_descriptor_helper_is_protected(string $expected, string $method, string ...$arguments): void
    {
        $trait = in_array($method, ['one', 'many'], true) ? Relations::class : Values::class;

        self::assertTrue((new ReflectionMethod($trait, $method))->isProtected());
    }
}
