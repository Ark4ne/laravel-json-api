<?php

declare(strict_types=1);

namespace Test\Unit\Resources\Concerns;

use Ark4ne\JsonApi\Compilation\Definitions;
use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Definition\ValueType;
use Ark4ne\JsonApi\Exceptions\DefinitionException;
use Ark4ne\JsonApi\Resources\JsonApiCollection;
use Ark4ne\JsonApi\Support\Probe;
use Test\app\Http\Resources\PostResource;
use Test\app\Http\Resources\UserCollection;
use Test\app\Http\Resources\UserResource;
use Test\Support\Reflect;
use Test\TestCase;
use Test\Unit\Compilation\Fixtures\PredefinedResource;

/**
 * On the application of `tests/app`: the guessed relationships (`$this->one()`) build their Eloquent relation.
 */
final class DefinableTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Definitions::configure(Configuration::defaults());
    }

    public function test_a_resource_gives_its_definition_without_a_model(): void
    {
        $definition = PostResource::definition();

        self::assertSame('post', $definition->type);
        self::assertSame(['state', 'title', 'content'], array_keys($definition->attributes));
        self::assertSame(['user', 'comments'], array_keys($definition->relationships));
    }

    public function test_the_definition_is_the_cached_one(): void
    {
        self::assertSame(Definitions::for(PostResource::class), PostResource::definition());
    }

    public function test_a_collection_gives_the_definition_of_the_resource_it_collects(): void
    {
        self::assertSame(UserResource::definition(), UserCollection::definition());
    }

    public function test_a_collection_collecting_no_resource_throws(): void
    {
        $this->expectException(DefinitionException::class);

        JsonApiCollection::definition();
    }

    public function test_to_definition_replaces_the_compilation(): void
    {
        $definition = PredefinedResource::definition();

        self::assertSame('predefined', $definition->type);
        self::assertSame(ValueType::String, $definition->attribute('title')?->type);
    }

    public function test_a_definition_source_holds_the_probe_as_its_resource(): void
    {
        $probe = Probe::root();

        self::assertSame($probe, Reflect::get(PostResource::newDefinitionSource($probe), 'resource'));
    }
}
