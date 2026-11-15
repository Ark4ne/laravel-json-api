<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Definition;

use Ark4ne\ApiRevisions\Revisions\Revision;
use Ark4ne\ApiRevisions\Revisions\RevisionOrder as BaseOrder;
use Ark4ne\JsonApi\Exceptions\DefinitionException;

/**
 * The order of the revisions, as `ark4ne/laravel-api-revisions` gives it (the `order()` of their enum), for the shapes
 * this package still selects itself: `null` stands for the head, the newest revision.
 *
 * @internal Replaced by the shapes of the base (docs/rfc/api-revisions-integration.md EJ3).
 */
final readonly class RevisionOrder
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

        return BaseOrder::index($a) <=> BaseOrder::index($b);
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
        return self::pick(-1, ...$revisions);
    }

    /**
     * The newest of the revisions, `null` for none.
     *
     * @throws DefinitionException when the revisions are cases of two enums
     */
    public static function newest(Revision ...$revisions): ?Revision
    {
        return self::pick(1, ...$revisions);
    }

    /**
     * The revisions each once, oldest first.
     *
     * @return list<Revision>
     *
     * @throws DefinitionException when the revisions are cases of two enums
     */
    public static function sorted(Revision ...$revisions): array
    {
        $unique = [];
        foreach ($revisions as $revision) {
            if (!in_array($revision, $unique, true)) {
                $unique[] = $revision;
            }
        }
        usort($unique, self::compare(...));

        return $unique;
    }

    /**
     * The boundary whose shape a revision sees: the oldest of `$boundaries` at least as new as `$revision`, `null` for
     * the head shape. A revision newer than every boundary reads the head in one comparison.
     *
     * @param list<Revision> $boundaries Oldest first (`sorted()`)
     *
     * @throws DefinitionException when the revisions are cases of two enums
     */
    public static function boundaryOf(array $boundaries, ?Revision $revision): ?Revision
    {
        $newest = $boundaries === [] ? null : $boundaries[count($boundaries) - 1];
        if ($revision === null || $newest === null || self::compare($revision, $newest) > 0) {
            return null;
        }

        foreach ($boundaries as $boundary) {
            if (self::compare($boundary, $revision) >= 0) {
                return $boundary;
            }
        }

        return null;
    }

    /**
     * The revision right before `$revision`, `null` for the oldest one.
     */
    public static function previous(Revision $revision): ?Revision
    {
        return BaseOrder::previous($revision);
    }

    /**
     * The revision the furthest in the direction `$sign` (`-1` the oldest, `1` the newest); every revision is compared,
     * so cases of two enums always throw.
     *
     * @param -1|1 $sign
     */
    private static function pick(int $sign, Revision ...$revisions): ?Revision
    {
        $picked = null;
        foreach ($revisions as $revision) {
            if ($picked === null || self::compare($revision, $picked) * $sign > 0) {
                $picked = $revision;
            }
        }

        return $picked;
    }
}
