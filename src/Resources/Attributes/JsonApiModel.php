<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Resources\Attributes;

use Attribute;

/**
 * The Eloquent model of the resource, for the relationships declared without resource class (`$this->one('author')`)
 * when it does not follow the convention (`App\Http\Resources\PostResource` → `App\Models\Post`):
 * `#[JsonApiModel(Post::class)]`.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class JsonApiModel
{
    /**
     * @param class-string $model
     */
    public function __construct(public string $model) {}
}
