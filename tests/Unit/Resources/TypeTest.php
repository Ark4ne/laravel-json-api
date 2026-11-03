<?php

declare(strict_types=1);

namespace Test\Unit\Resources;

use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;
use Test\TestCase;
use Test\Unit\Compilation\Fixtures\AttributeTypedResource;
use Test\Unit\Compilation\Fixtures\BlogPostResource;

/**
 * The default `toType()` gives the type of the definition: `#[JsonApiType]`, otherwise the configured naming
 * convention; an override calling it reads the same.
 */
final class TypeTest extends TestCase
{
    public function test_a_resource_renders_the_type_of_its_attribute(): void
    {
        self::assertSame(
            ['type' => 'articles', 'id' => '1'],
            (new AttributeTypedResource((object) ['id' => 1]))->toArray(new Request()),
        );
    }

    public function test_a_resource_renders_the_type_of_the_naming_convention(): void
    {
        self::assertSame(
            ['type' => 'blog-post', 'id' => '2'],
            (new BlogPostResource((object) ['id' => 2]))->toArray(new Request()),
        );
    }

    public function test_an_override_calling_the_parent_gets_the_type_of_the_definition(): void
    {
        $resource = new class ((object) ['id' => 3]) extends JsonApiResource {
            protected function toType(Request $request): string
            {
                return 'v2-' . parent::toType($request);
            }
        };

        self::assertSame(['type' => 'v2-anonymous', 'id' => '3'], $resource->toArray(new Request()));
    }
}
