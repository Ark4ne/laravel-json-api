<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Descriptors;

use Ark4ne\JsonApi\Definition\Revision;
use Ark4ne\JsonApi\Definition\RevisionOrder;
use Closure;

/**
 * The shape of a field for a revision (docs/rfc/versioned-resources-epics.md §1.2-1.4): one pure function, shared by
 * the compilation and the rendering so that they never disagree.
 *
 * @internal
 */
final readonly class FieldHistory
{
    /**
     * The name and the descriptor of a field at `$requested` (`null` for the head), `null` when the field does not
     * exist there (`until()`, `since()`).
     *
     * One `was()` applies: the one with the oldest revision at least as new as `$requested`, whatever the order of the
     * calls; none for the head. What it leaves out is the head's: `name:` alone keeps the head descriptor, `value:`
     * replaces it entirely. The model has not changed: a descriptor without source of its own reads the head source
     * (its attribute or relation, otherwise the head name); a head read by a closure gives none (`null` source).
     *
     * @template T
     *
     * @param Describer<T> $head
     *
     * @return array{string, Describer<T>}|null
     *
     * @throws \Ark4ne\JsonApi\Exceptions\DefinitionException when the revisions are cases of two enums
     */
    public static function at(Describer $head, string $headName, ?Revision $requested): ?array
    {
        $since = $head->sinceRevision();
        $until = $head->untilRevision();
        if (
            ($since !== null && !RevisionOrder::atLeast($requested, $since))
            || ($until !== null && !RevisionOrder::atMost($requested, $until))
        ) {
            return null;
        }

        $applied = $requested === null ? null : self::applied($head->revisions(), $requested);
        if ($applied === null) {
            return [$headName, $head];
        }

        $descriptor = $applied->value ?? $head;
        $source = $head->retriever() ?? $headName;

        return [
            $applied->name ?? $headName,
            $source instanceof Closure ? $descriptor : $descriptor->readingFrom($source),
        ];
    }

    /**
     * The `was()` with the oldest revision at least as new as `$requested`, `null` for none.
     *
     * @template T
     *
     * @param list<FieldRevision<T>> $revisions
     *
     * @return FieldRevision<T>|null
     */
    private static function applied(array $revisions, Revision $requested): ?FieldRevision
    {
        $applied = null;
        foreach ($revisions as $revision) {
            if (
                RevisionOrder::compare($revision->revision, $requested) >= 0
                && ($applied === null || RevisionOrder::compare($revision->revision, $applied->revision) < 0)
            ) {
                $applied = $revision;
            }
        }

        return $applied;
    }
}
