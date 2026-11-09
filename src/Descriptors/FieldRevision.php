<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Descriptors;

use Ark4ne\JsonApi\Definition\Revision;

/**
 * What a field was up to a revision, included (`was()`): its name, its descriptor, or both; what is left out is the
 * head's (docs/rfc/versioned-resources-epics.md §1.3).
 *
 * @template T The model the descriptors read
 */
final readonly class FieldRevision
{
    /**
     * @param Describer<T>|null $value
     */
    public function __construct(
        public Revision $revision,
        public ?string $name = null,
        public ?Describer $value = null,
    ) {}
}
