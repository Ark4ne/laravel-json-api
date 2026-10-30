<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Definition;

enum Cardinality: string
{
    /** To-one: the linkage is a resource identifier or `null`. */
    case One = 'one';

    /** To-many: the linkage is a list of resource identifiers. */
    case Many = 'many';
}
