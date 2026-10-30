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
final class AlphaResource extends JsonApiResource
{
    protected static function toDefinition(): ResourceDefinition
    {
        return ResourceDefinition::of(self::class, 'alpha')
            ->attributes(
                AttributeDefinition::of('title', ValueType::String),
                AttributeDefinition::of('size', ValueType::Struct)
                    ->children(AttributeDefinition::of('width', ValueType::Integer)),
            )
            ->relationships(
                RelationshipDefinition::one('beta', BetaResource::class)->load(LoadDefinition::of(['b'])),
                RelationshipDefinition::many('gammas', GammaResource::class)->load(LoadDefinition::of(['g'])),
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
