<?php

declare(strict_types=1);

namespace Test\Unit\Definition;

use Ark4ne\JsonApi\Definition\RelatedResource;
use Ark4ne\JsonApi\Exceptions\DefinitionException;
use Ark4ne\JsonApi\Resources\JsonApiCollection;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Resources\Attributes\Collects;
use Illuminate\Http\Resources\JsonApi\JsonApiResource as NativeJsonApiResource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Test\app\Http\Resources\NativeUserResource;

final class RelatedResourceTest extends TestCase
{
    public function test_a_resource_relates_to_itself(): void
    {
        self::assertSame(ConventionResource::class, RelatedResource::resolve(ConventionResource::class));
    }

    public function test_a_collection_relates_to_the_resource_of_its_collects_property(): void
    {
        self::assertSame(ConventionResource::class, RelatedResource::resolve(PropertyCollection::class));
    }

    public function test_a_collection_relates_to_the_resource_of_its_collects_attribute(): void
    {
        if (!class_exists(Collects::class)) {
            self::markTestSkipped('#[Collects] exists from Laravel 13.');
        }

        self::assertSame(ConventionResource::class, RelatedResource::resolve(AttributeCollection::class));
    }

    public function test_a_collection_relates_to_the_resource_named_after_it(): void
    {
        self::assertSame(ConventionResource::class, RelatedResource::resolve(ConventionCollection::class));
        self::assertSame(ConventionResource::class, ConventionCollection::definition()->class);
    }

    /**
     * @return iterable<string, array{class-string, bool}>
     */
    public static function classes(): iterable
    {
        yield 'a native resource' => [NativeUserResource::class, true];
        yield 'the native base resource' => [NativeJsonApiResource::class, true];
        yield 'a resource of the package' => [ConventionResource::class, false];
    }

    /**
     * @param class-string $class
     */
    #[DataProvider('classes')]
    public function test_a_resource_of_laravel_native_json_api_is_native(string $class, bool $native): void
    {
        self::assertSame($native, RelatedResource::isNative($class));
    }

    public function test_a_class_that_is_not_a_resource_throws(): void
    {
        $this->expectException(DefinitionException::class);

        RelatedResource::resolve(self::class);
    }
}

/**
 * @extends JsonApiResource<object>
 */
final class ConventionResource extends JsonApiResource {}

/**
 * @extends JsonApiCollection<ConventionResource>
 */
final class ConventionCollection extends JsonApiCollection {}

/**
 * @extends JsonApiCollection<ConventionResource>
 */
final class PropertyCollection extends JsonApiCollection
{
    public $collects = ConventionResource::class;
}

/**
 * @extends JsonApiCollection<ConventionResource>
 */
#[Collects(ConventionResource::class)]
final class AttributeCollection extends JsonApiCollection {}
