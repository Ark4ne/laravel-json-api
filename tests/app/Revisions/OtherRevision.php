<?php

declare(strict_types=1);

namespace Test\app\Revisions;

use Ark4ne\ApiRevisions\Revisions\IsRevision;
use Ark4ne\ApiRevisions\Revisions\Revision;

/**
 * A second enum of revisions: comparing its cases with those of `ApiRevision` is a programming error.
 */
enum OtherRevision: string implements Revision
{
    use IsRevision;

    case First = 'first';
    /** Named as a case of `ApiRevision`: `jsonapi:check --revision=Labels` reads both. */
    case Labels = 'labels';

    public static function order(): array
    {
        return [self::First, self::Labels];
    }
}
