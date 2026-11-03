<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Descriptors\Relations;

use Ark4ne\JsonApi\Configuration\Linkage;
use Ark4ne\JsonApi\Definition\Cardinality;
use Ark4ne\JsonApi\Filters\Filters;
use Closure;

/**
 * What a relation descriptor renders for one resource: the resource class of the related ones, how the related value is
 * read (a `MissingValue` when a condition fails), and the links, meta, linkage, polymorphic map and filters declared.
 * The resource turns it into a `Resources\Relationship`: the descriptors do not depend on the resources.
 *
 * @internal
 */
final readonly class DeclaredRelationship
{
    /**
     * @param class-string $related The resource (or collection) class of the related resources
     * @param Closure(): mixed $value Reads the related value
     * @param array<class-string, class-string> $morphs Resource class by model class
     */
    public function __construct(
        public string $related,
        public Cardinality $cardinality,
        public Closure $value,
        public ?Closure $links = null,
        public ?Closure $meta = null,
        public ?Closure $identifierMeta = null,
        public ?Linkage $linkage = null,
        public array $morphs = [],
        public ?Filters $filters = null,
    ) {}
}
