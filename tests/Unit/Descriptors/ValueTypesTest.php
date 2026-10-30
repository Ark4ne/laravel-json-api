<?php

declare(strict_types=1);

namespace Test\Unit\Descriptors;

use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Descriptors\Values\ValueInteger;
use Ark4ne\JsonApi\Descriptors\Values\ValueStruct;
use Ark4ne\JsonApi\Descriptors\ValueTypes;
use Ark4ne\JsonApi\Exceptions\DefinitionException;
use Ark4ne\JsonApi\Exceptions\InvalidConfigurationException;
use PHPUnit\Framework\TestCase;
use stdClass;
use Test\app\Values\MoneyValue;

final class ValueTypesTest extends TestCase
{
    private function configuration(): Configuration
    {
        return Configuration::fromArray(['describer' => ['types' => ['money' => MoneyValue::class]]]);
    }

    public function test_a_type_of_the_package_is_made_by_its_name(): void
    {
        self::assertInstanceOf(ValueInteger::class, ValueTypes::make(Configuration::defaults(), 'integer'));
    }

    public function test_a_registered_type_is_made_by_its_name_for_an_attribute(): void
    {
        $value = ValueTypes::make($this->configuration(), 'money', 'price');

        self::assertInstanceOf(MoneyValue::class, $value);
        self::assertSame('price', $value->retriever());
    }

    public function test_a_type_is_made_by_its_class(): void
    {
        self::assertInstanceOf(MoneyValue::class, ValueTypes::make(Configuration::defaults(), MoneyValue::class));
    }

    public function test_an_unknown_type_throws(): void
    {
        $this->expectException(DefinitionException::class);
        $this->expectExceptionMessage('[money]');

        ValueTypes::make(Configuration::defaults(), 'money');
    }

    public function test_a_type_needing_more_than_an_attribute_throws_naming_the_way_out(): void
    {
        $this->expectException(DefinitionException::class);
        $this->expectExceptionMessage('give an instance');

        ValueTypes::make(Configuration::defaults(), ValueStruct::class);
    }

    public function test_the_registered_types_are_checked(): void
    {
        ValueTypes::validate($this->configuration(), ['toArray']);

        $this->expectNotToPerformAssertions();
    }

    /**
     * @return iterable<string, array{array<string, class-string>, string}>
     */
    public static function invalid(): iterable
    {
        yield 'not a value' => [['money' => stdClass::class], 'describer.types.money'];
        yield 'a name of the package' => [['string' => MoneyValue::class], 'describer.types.string'];
        yield 'a method of the resources' => [['toArray' => MoneyValue::class], 'describer.types.toArray'];
    }

    /**
     * @param array<string, class-string> $types
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalid')]
    public function test_an_invalid_registered_type_throws_naming_the_key(array $types, string $key): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($key);

        ValueTypes::validate(Configuration::fromArray(['describer' => ['types' => $types]]), ['toArray']);
    }
}
