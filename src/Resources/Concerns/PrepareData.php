<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Resources\Concerns;

use Ark4ne\JsonApi\Configuration\Linkage;
use Ark4ne\JsonApi\Descriptors\Schemas\RelationshipSchema;

trait PrepareData
{
    protected ?bool $autoWhenHas = null;

    protected ?bool $autoWhenIncluded = null;

    /**
     * The linkage of the relationships of the resource, as it says (`$autoWhenIncluded`): only when included
     * (`true`), always for those that do not set theirs (`false`); `relationship.linkage` applies otherwise.
     *
     * @template TSchema of RelationshipSchema<mixed>
     *
     * @param TSchema $relationship
     *
     * @return TSchema
     */
    protected function autoWhenIncluded(RelationshipSchema $relationship): RelationshipSchema
    {
        return match ($this->autoWhenIncluded) {
            null => $relationship,
            true => $relationship->whenIncluded(),
            false => $relationship->defaultLinkage(Linkage::Always),
        };
    }
}
