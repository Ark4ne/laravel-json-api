<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Definition;

/**
 * The revisions a resource exists for (`#[JsonApiSince]`, `#[JsonApiUntil]`), bounds included: every revision and the
 * head when it declares none.
 */
final readonly class Lifetime
{
    public function __construct(
        public ?Revision $since = null,
        public ?Revision $until = null,
    ) {}

    /**
     * Whether the resource exists at `$revision` (`null` for the head).
     *
     * @throws \Ark4ne\JsonApi\Exceptions\DefinitionException when the revisions are cases of two enums
     */
    public function covers(?Revision $revision): bool
    {
        return ($this->since === null || RevisionOrder::atLeast($revision, $this->since))
            && ($this->until === null || RevisionOrder::atMost($revision, $this->until));
    }
}
