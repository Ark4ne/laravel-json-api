<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Definition;

use Ark4ne\ApiRevisions\Revisions\Revision;
use Ark4ne\ApiRevisions\Revisions\RevisionOrder;
use Ark4ne\JsonApi\Exceptions\DefinitionException;

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
     * Whether it covers no revision: its `since` is newer than its `until`.
     *
     * @throws DefinitionException when the revisions are cases of two enums
     */
    public function isEmpty(): bool
    {
        return $this->since !== null && $this->until !== null && self::compare($this->since, $this->until) > 0;
    }

    /**
     * Whether the resource exists at `$revision` (`null` for the head).
     *
     * @throws DefinitionException when the revisions are cases of two enums
     */
    public function covers(?Revision $revision): bool
    {
        $bound = $this->since ?? $this->until;
        if ($bound === null) {
            return true;
        }

        // The head is the newest revision of the enum of the bounds.
        $revision ??= RevisionOrder::latest($bound::class);

        return ($this->since === null || self::compare($revision, $this->since) >= 0)
            && ($this->until === null || self::compare($revision, $this->until) <= 0);
    }

    /**
     * Negative when `$a` is older than `$b`, zero when they are the same, positive when `$a` is newer.
     *
     * @throws DefinitionException when the revisions are cases of two enums
     */
    private static function compare(Revision $a, Revision $b): int
    {
        if ($a::class !== $b::class) {
            throw DefinitionException::mixedRevisions($a::class, $b::class);
        }

        return RevisionOrder::index($a) <=> RevisionOrder::index($b);
    }
}
