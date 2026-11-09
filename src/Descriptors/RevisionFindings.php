<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Descriptors;

/**
 * What `RevisedMembers` sees while it transforms members, for the compilation to check: the fields declaring a
 * revision (`was()`, `until()`, `since()`) and the names two members take in one revision. The members of a struct are
 * seen when the struct reads them, so that it is read once.
 *
 * @internal
 */
final class RevisionFindings
{
    /** @var list<array{string, Describer<mixed>}> */
    private array $fields = [];

    /** @var list<string> */
    private array $collisions = [];

    /**
     * @param Describer<mixed> $field
     */
    public function field(string $name, Describer $field): void
    {
        if (
            $field->revisions() !== []
            || $field->sinceRevision() !== null
            || $field->untilRevision() !== null
            || $field->redeclaredBounds() !== []
        ) {
            $this->fields[] = [$name, $field];
        }
    }

    public function collision(string $name): void
    {
        $this->collisions[] = $name;
    }

    /**
     * The fields declaring a revision, by their head name.
     *
     * @return list<array{string, Describer<mixed>}>
     */
    public function fields(): array
    {
        return $this->fields;
    }

    /**
     * The names taken by two members of one hook.
     *
     * @return list<string>
     */
    public function collisions(): array
    {
        return $this->collisions;
    }
}
