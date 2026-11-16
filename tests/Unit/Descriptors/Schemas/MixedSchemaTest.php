<?php

declare(strict_types=1);

namespace Test\Unit\Descriptors\Schemas;

use Ark4ne\ApiRevisions\Definition\Kind;
use Ark4ne\ApiRevisions\Schema\InvalidValue;
use Ark4ne\JsonApi\Descriptors\Schemas\MixedSchema;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

final class MixedSchemaTest extends TestCase
{
    public function test_any_value_is_kept_as_it_is(): void
    {
        $mixed = new MixedSchema();

        self::assertSame('text', $mixed->normalize('text'));
        self::assertSame(['a' => [1, 2.5, false]], $mixed->normalize(['a' => [1, 2.5, false]]));
        self::assertSame(0, $mixed->serialize($mixed->normalize(0)));
    }

    public function test_null_is_refused_as_every_null(): void
    {
        self::assertInstanceOf(InvalidValue::class, (new MixedSchema())->normalize(null));
    }

    public function test_the_type_is_any(): void
    {
        self::assertSame(Kind::Any, (new MixedSchema())->toType()->kind);
    }

    public function test_arrayable_values_are_rendered_as_plain_arrays_at_any_depth(): void
    {
        $value = new Collection(['a' => 1, 'nested' => ['inner' => new Collection([1, 2])]]);

        self::assertSame(['a' => 1, 'nested' => ['inner' => [1, 2]]], (new MixedSchema())->serialize($value));
        self::assertSame('plain', (new MixedSchema())->serialize('plain'));
    }
}
