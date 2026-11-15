<?php

declare(strict_types=1);

namespace Benchmark\Revised;

use Ark4ne\ApiRevisions\Revisions\IsRevision;

/**
 * The revisions of the revised benchmark resources; the last one of `order()` is the head.
 */
enum Revision: string implements \Ark4ne\ApiRevisions\Revisions\Revision
{
    use IsRevision;

    case Oldest = '2025-01-01';
    case Middle = '2025-06-01';
    case Newest = '2026-01-01';

    public static function order(): array
    {
        return [self::Oldest, self::Middle, self::Newest];
    }

    /**
     * The include paths of the scenario, in the names of the revision.
     */
    public static function include(?self $revision): string
    {
        return $revision === null || $revision === self::Newest
            ? 'user,comments,comments.user'
            : 'author,comments,comments.user';
    }
}
