<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Descriptors;

use Ark4ne\JsonApi\Definition\Lifetime;
use Ark4ne\JsonApi\Definition\Revision;
use Ark4ne\JsonApi\Definition\RevisionOrder;

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
     * The name returned is the key the field is rendered under; the descriptor returned stands for the head field
     * (`Describer::standingFor()`): whatever `$attribute` it is given, its conditions (`whenHas()`, `whenNotNull()`,
     * `whenLoaded()`…), its source and its closures receive the head name (model side, always the head). Two `was()` with the same revision: the first call wins, and the compilation reports
     * it (`revision-duplicate`).
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
        if (!(new Lifetime($head->sinceRevision(), $head->untilRevision()))->covers($requested)) {
            return null;
        }

        $applied = $requested === null ? null : self::applied($head->revisions(), $requested);
        if ($applied === null) {
            return [$headName, $head];
        }

        return [
            $applied->name ?? $headName,
            ($applied->value ?? $head)->standingFor($headName, $head->retriever() ?? $headName),
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
