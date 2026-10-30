<?php

declare(strict_types=1);

namespace Test\Unit\Resources\Fixtures;

use Ark4ne\JsonApi\Definition\AttributeDefinition;
use Ark4ne\JsonApi\Definition\LoadDefinition;
use Ark4ne\JsonApi\Definition\RelationshipDefinition;
use Ark4ne\JsonApi\Definition\ResourceDefinition;
use Ark4ne\JsonApi\Definition\ValueType;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;
use LogicException;

/**
 * Writes its definition by hand (one of the cycle alpha → beta → gamma → alpha): whatever reads the definition never runs its hooks.
 *
 * @extends JsonApiResource<mixed>
 */
final class GammaResource extends JsonApiResource
{
    protected static function toDefinition(): ResourceDefinition
    {
        return ResourceDefinition::of(self::class, 'gamma')
            ->attributes(AttributeDefinition::of('label', ValueType::String))
            ->relationships(
                RelationshipDefinition::one('alpha', AlphaResource::class)->load(LoadDefinition::of(['a'])),
                RelationshipDefinition::many('lazy', AlphaResource::class),
            );
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
