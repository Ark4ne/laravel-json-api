<?php

declare(strict_types=1);

namespace Test\Feature\Values;

use Ark4ne\JsonApi\Definition\ValueType;
use Ark4ne\JsonApi\Exceptions\InvalidConfigurationException;
use Ark4ne\JsonApi\Laravel\JsonApi;
use Illuminate\Http\Request;
use Test\app\Values\MoneyValue;
use Test\Feature\FeatureTestCase;
use Test\Feature\Values\Fixtures\PriceResource;

/**
 * A value type registered by the application (`JsonApi::valueType()`) is written `$this->money()` and named as the
 * items of an array, and its definition is that of its class.
 */
final class ValueTypeTest extends FeatureTestCase
{
    public function test_a_registered_value_type_renders_and_is_defined(): void
    {
        JsonApi::valueType('money', MoneyValue::class);

        $resource = new PriceResource((object) ['id' => 1, 'price' => 1250, 'prices' => [100, 5]]);
        $definition = PriceResource::definition();

        self::assertSame(['price' => '12.50', 'prices' => ['1.00', '0.05']], $resource->toArray(new Request())['attributes'] ?? null);
        $price = $definition->attribute('price');
        self::assertNotNull($price);
        self::assertSame(ValueType::String, $price->type);
        self::assertSame('money', $price->format);
        self::assertSame('money', $definition->attribute('prices')?->items?->format);
    }

    public function test_a_value_type_cannot_take_the_name_of_a_method_of_the_resources(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('describer.types.toArray');

        JsonApi::valueType('toArray', MoneyValue::class);
    }

    public function test_a_call_that_is_not_a_value_type_goes_to_the_model(): void
    {
        $resource = new PriceResource(new class {
            public function total(): int
            {
                return 3;
            }
        });

        self::assertSame(3, $resource->__call('total', []));
    }
}
