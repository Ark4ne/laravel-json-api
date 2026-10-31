<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Descriptors\Values;

use Ark4ne\JsonApi\Configuration\Configuration;

/**
 * A time of day, `H:i:s` (`14:30:00`) unless `format()` says otherwise; a string such as `14:30` is read as today's.
 *
 * @template T
 * @extends ValueDate<T>
 */
final class ValueTime extends ValueDate
{
    protected function defaultFormat(Configuration $configuration): string
    {
        return 'H:i:s';
    }
}
