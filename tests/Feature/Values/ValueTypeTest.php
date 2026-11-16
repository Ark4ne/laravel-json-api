<?php

declare(strict_types=1);

namespace Test\Feature\Values;

use Ark4ne\ApiRevisions\Schema\TypeRegistry;
use Ark4ne\JsonApi\Definition\ValueType;
use Illuminate\Http\Request;
use Test\app\Values\MoneySchema;
use Test\Feature\FeatureTestCase;
use Test\Feature\Values\Fixtures\PriceResource;

/**
 * A type the application registers in `ark4ne/laravel-api-revisions` (`TypeRegistry::register()`) is written
 * `$this->money()`, also as the items of an array, and its definition is that of its class.
 */
final class ValueTypeTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        TypeRegistry::register('money', MoneySchema::class);
    }

    protected function tearDown(): void
    {
        TypeRegistry::flush();

        parent::tearDown();
    }

    public function test_a_registered_type_renders_and_is_defined(): void
    {
        $resource = new PriceResource((object) ['id' => 1, 'price' => 1250, 'prices' => [100, 5]]);
        $definition = PriceResource::definition();

        self::assertSame(['price' => '12.50', 'prices' => ['1.00', '0.05']], $resource->toArray(new Request())['attributes'] ?? null);
        $price = $definition->attribute('price');
        self::assertNotNull($price);
        self::assertSame(ValueType::String, $price->type);
        self::assertSame('money', $price->format);
        self::assertSame('money', $definition->attribute('prices')?->items?->format);
    }

    public function test_a_call_that_is_not_a_registered_type_goes_to_the_model(): void
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
