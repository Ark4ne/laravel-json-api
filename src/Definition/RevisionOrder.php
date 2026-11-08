<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Definition;

use Ark4ne\JsonApi\Exceptions\DefinitionException;

/**
 * The order of the revisions: the declaration order of the cases of their enum, the oldest first. The head (`null`)
 * is newer than every revision.
 */
final class RevisionOrder
{
    /**
     * Negative when `$a` is older than `$b`, zero when they are the same, positive when `$a` is newer.
     *
     * @throws DefinitionException when the revisions are cases of two enums
     */
    public static function compare(Revision $a, Revision $b): int
    {
        if ($a::class !== $b::class) {
            throw DefinitionException::mixedRevisions($a::class, $b::class);
        }

        return self::position($a) <=> self::position($b);
    }

    /**
     * Whether `$requested` is `$bound` or an older revision; the head is never.
     *
     * @throws DefinitionException when the revisions are cases of two enums
     */
    public static function atMost(?Revision $requested, Revision $bound): bool
    {
        return $requested !== null && self::compare($requested, $bound) <= 0;
    }

    /**
     * Whether `$requested` is `$bound` or a newer revision; the head always is.
     *
     * @throws DefinitionException when the revisions are cases of two enums
     */
    public static function atLeast(?Revision $requested, Revision $bound): bool
    {
        return $requested === null || self::compare($requested, $bound) >= 0;
    }

    /**
     * The oldest of the revisions, `null` for none.
     *
     * @throws DefinitionException when the revisions are cases of two enums
     */
    public static function oldest(Revision ...$revisions): ?Revision
    {
        $oldest = null;
        foreach ($revisions as $revision) {
            if ($oldest === null || self::compare($revision, $oldest) < 0) {
                $oldest = $revision;
            }
        }

        return $oldest;
    }

    /**
     * The newest of the revisions, `null` for none.
     *
     * @throws DefinitionException when the revisions are cases of two enums
     */
    public static function newest(Revision ...$revisions): ?Revision
    {
        $newest = null;
        foreach ($revisions as $revision) {
            if ($newest === null || self::compare($revision, $newest) > 0) {
                $newest = $revision;
            }
        }

        return $newest;
    }

    /**
     * The revision right before `$revision`, `null` for the oldest case.
     */
    public static function previous(Revision $revision): ?Revision
    {
        $previous = $revision::cases()[self::position($revision) - 1] ?? null;

        return $previous instanceof Revision ? $previous : null;
    }

    /**
     * The index of the case in the declaration of its enum.
     */
    private static function position(Revision $revision): int
    {
        return (int) array_search($revision, $revision::cases(), true);
    }
}
