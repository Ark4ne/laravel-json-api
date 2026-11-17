<?php

declare(strict_types=1);

namespace Test\app\Revisions;

use Ark4ne\ApiRevisions\Revisions\IsRevision;
use Ark4ne\ApiRevisions\Revisions\Meta;
use Ark4ne\ApiRevisions\Revisions\Revision;

/**
 * The revisions of the test application; the last one of `order()` is the head.
 */
enum ApiRevision: string implements Revision
{
    use IsRevision;

    #[Meta('First published revision')]
    case Initial = '2026-09-01';
    #[Meta('Names become labels')]
    case Labels = '2026-12-01';
    #[Meta('Users replace teachers, authors and attendees')]
    case UsersReplaceTeachers = '2027-01-15';

    public static function order(): array
    {
        return [self::Initial, self::Labels, self::UsersReplaceTeachers];
    }
}
