<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Definition;

use Ark4ne\JsonApi\Exceptions\DefinitionException;

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
        $position = array_search($revision, $cases ?? self::cases($revision), true);

        return is_int($position) ? $position : throw DefinitionException::unknownRevision($revision::class, $revision->name);
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
