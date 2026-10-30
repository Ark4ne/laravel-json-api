<?php

declare(strict_types=1);

namespace Test\Unit\Compilation\Fixtures;

use Ark4ne\JsonApi\Definition\AttributeDefinition;
use Ark4ne\JsonApi\Definition\ResourceDefinition;
use Ark4ne\JsonApi\Definition\ValueType;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;
use LogicException;

/**
 * Writes its definition by hand: its hooks must never run on a probe.
 *
 * @extends JsonApiResource<\Test\app\Models\Post>
 */
final class PredefinedResource extends JsonApiResource
{
    protected static function toDefinition(): ResourceDefinition
    {
        return ResourceDefinition::of(self::class, 'predefined')
            ->attributes(AttributeDefinition::of('title', ValueType::String));
    }

    protected function toType(Request $request): string
    {
        throw new LogicException('Never probed.');
    }

    public function toAttributes(Request $request): iterable
    {
        throw new LogicException('Never probed.');
    }
}
