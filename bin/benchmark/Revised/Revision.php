<?php

declare(strict_types=1);

namespace Benchmark\Revised;

/**
 * The revisions of the revised benchmark resources, the oldest first.
 */
enum Revision: string implements \Ark4ne\JsonApi\Definition\Revision
{
    case Oldest = '2025-01-01';
    case Middle = '2025-06-01';
    case Newest = '2026-01-01';

    public function description(): string
    {
        return $this->name;
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
