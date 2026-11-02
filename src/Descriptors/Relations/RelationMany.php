<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Descriptors\Relations;

use Ark4ne\JsonApi\Definition\Cardinality;

/**
 * @template T
 * @extends Relation<T>
 */
final class RelationMany extends Relation
{
    protected function cardinality(): Cardinality
    {
        return Cardinality::Many;
    }
}
