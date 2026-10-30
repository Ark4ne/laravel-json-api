<?php

declare(strict_types=1);

namespace Test\Unit\Loading\Fixtures;

use Ark4ne\JsonApi\Configuration\Linkage;
use Ark4ne\JsonApi\Definition\LoadDefinition;
use Ark4ne\JsonApi\Definition\RelationshipDefinition;
use Ark4ne\JsonApi\Definition\ResourceDefinition;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;
use LogicException;

/**
 * Writes its definition by hand: one relationship per linkage, each read from the relation of its name.
 *
 * @extends JsonApiResource<mixed>
 */
final class DeltaResource extends JsonApiResource
{
    protected static function toDefinition(): ResourceDefinition
    {
        return ResourceDefinition::of(self::class, 'delta')->relationships(
            ...array_map(
                static fn(string $name, Linkage $linkage) => RelationshipDefinition::one($name, self::class)
                    ->load(LoadDefinition::of([$name]))
                    ->source($name)
                    ->linkage($linkage),
                ['always', 'loaded', 'never'],
                [Linkage::Always, Linkage::Loaded, Linkage::Included],
            ),
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
