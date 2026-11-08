<?php

declare(strict_types=1);

namespace Test\app\Revisions;

use Ark4ne\JsonApi\Definition\Revision;

/**
 * A second enum of revisions: comparing its cases with those of `ApiRevision` is a programming error.
 */
enum OtherRevision implements Revision
{
    case First;

    public function description(): string
    {
        return 'First';
    }
}
