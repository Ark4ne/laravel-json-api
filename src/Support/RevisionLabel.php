<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Support;

use UnitEnum;

/**
 * How a revision is named in the messages and the keys of the package: `Enum::Case`, `head` for none.
 */
final class RevisionLabel
{
    public static function of(?UnitEnum $revision): string
    {
        return $revision === null ? 'head' : $revision::class . '::' . $revision->name;
    }
}
