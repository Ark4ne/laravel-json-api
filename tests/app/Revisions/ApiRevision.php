<?php

declare(strict_types=1);

namespace Test\app\Revisions;

use Ark4ne\JsonApi\Definition\Revision;

/**
 * The revisions of the test application, the oldest first.
 */
enum ApiRevision: string implements Revision
{
    case Initial = '2026-09-01';
    case Labels = '2026-12-01';
    case UsersReplaceTeachers = '2027-01-15';

    public function description(): string
    {
        return match ($this) {
            self::Initial => 'First published revision',
            self::Labels => 'Names become labels',
            self::UsersReplaceTeachers => 'Users replace teachers, authors and attendees',
        };
    }
}
