<?php

namespace Test\Unit\Descriptors;

use Ark4ne\JsonApi\Descriptors\Relations;
use Ark4ne\JsonApi\Descriptors\Relations\RelationMany;
use Ark4ne\JsonApi\Descriptors\Relations\RelationOne;
use Ark4ne\JsonApi\Descriptors\Values;
use Ark4ne\JsonApi\Descriptors\Values\ValueArray;
use Ark4ne\JsonApi\Descriptors\Values\ValueBool;
use Ark4ne\JsonApi\Descriptors\Values\ValueDate;
use Ark4ne\JsonApi\Descriptors\Values\ValueEnum;
use Ark4ne\JsonApi\Descriptors\Values\ValueFloat;
use Ark4ne\JsonApi\Descriptors\Values\ValueInteger;
use Ark4ne\JsonApi\Descriptors\Values\ValueMixed;
use Ark4ne\JsonApi\Descriptors\Values\ValueString;
use Ark4ne\JsonApi\Resources\JsonApiCollection;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Closure;
use PHPUnit\Framework\Attributes\DataProvider;
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
    public function testDescriptorTrait($expected, $method, ...$args)
    {
        $mock = new class extends stdClass {
            use Values;
            use Relations;
        };
        /** @var \Ark4ne\JsonApi\Descriptors\Describer $descriptor */
        $descriptor = Reflect::invoke($mock, $method, ...$args);
        $this->assertInstanceOf($expected, $descriptor);
        $this->assertNull($descriptor->retriever());
        $descriptor = Reflect::invoke($mock, $method, ...[...$args, 'test']);
        $this->assertInstanceOf($expected, $descriptor);
        $this->assertEquals('test', $descriptor->retriever());
        $descriptor = Reflect::invoke($mock, $method, ...[...$args, fn() => 'closure']);
        $this->assertInstanceOf($expected, $descriptor);
        $this->assertInstanceOf(Closure::class, $descriptor->retriever());
    }
}
