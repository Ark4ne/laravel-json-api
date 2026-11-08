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
    /** Named as a case of `ApiRevision`: `jsonapi:check --revision=Labels` reads both. */
    case Labels;

    public function description(): string
    {
        return $this->name;
    }
}
