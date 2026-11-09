<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Definition;

use Ark4ne\JsonApi\Exceptions\DefinitionException;
use Ark4ne\JsonApi\Support\Memo;

/**
 * The order of the revisions: the declaration order of the cases of their enum, the oldest first. The head (`null`)
 * is newer than every revision.
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
     * The revision right before `$revision`, `null` for the oldest case.
     */
    public static function previous(Revision $revision): ?Revision
    {
        $cases = self::cases($revision);

        return $cases[self::position($revision, $cases) - 1] ?? null;
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

    /**
     * The index of the case in the declaration of its enum.
     *
     * @param list<Revision>|null $cases The cases of its enum, when already read
     */
    private static function position(Revision $revision, ?array $cases = null): int
    {
        // Compared on every field of every resource rendered at a revision: the positions of an enum are read once.
        $positions = Memo::remember(self::class, $revision::class, static fn(): array => array_flip(array_map(
            static fn(Revision $case): string => $case->name,
            $cases ?? self::cases($revision),
        )));

        return $positions[$revision->name] ?? throw DefinitionException::unknownRevision($revision::class, $revision->name);
    }

    /**
     * @return list<Revision>
     */
    private static function cases(Revision $revision): array
    {
        /** @var list<Revision> */
        return $revision::cases();
    }
}
