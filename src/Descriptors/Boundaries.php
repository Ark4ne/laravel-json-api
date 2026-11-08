<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Descriptors;

use Ark4ne\JsonApi\Definition\Revision;

/**
 * The revisions where members change, gathered while `RevisedMembers` transforms them: the members of a struct are
 * read once, when the struct reads them.
 *
 * @internal
 */
final class Boundaries
{
    /** @var list<Revision> */
    private array $revisions = [];

    public function add(?Revision ...$revisions): void
    {
        foreach ($revisions as $revision) {
            if ($revision !== null) {
                $this->revisions[] = $revision;
            }
        }
    }

    /**
     * @return list<Revision> Unsorted, with repeats
     */
    public function all(): array
    {
        return $this->revisions;
    }
}
