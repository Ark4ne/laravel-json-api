<?php

declare(strict_types=1);

namespace Test\Feature\Links\Fixtures;

use Ark4ne\JsonApi\Definition\ResourceDefinition;
use Ark4ne\JsonApi\Resources\Attributes\JsonApiRoutes;
use Ark4ne\JsonApi\Resources\JsonApiResource;

/**
 * @extends JsonApiResource<\Test\app\Models\Post>
 */
#[JsonApiRoutes(self: 'posts.show')]
final class PredefinedRoutedPostResource extends JsonApiResource
{
    protected static function toDefinition(): ResourceDefinition
    {
        return ResourceDefinition::of(self::class, 'post');
    }
}
