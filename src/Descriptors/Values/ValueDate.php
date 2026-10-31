<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Descriptors\Values;

use Ark4ne\JsonApi\Configuration\Configuration;

/**
 * A calendar date without time, `Y-m-d` (`2026-10-30`) unless `format()` says otherwise.
 *
 * @template T
 * @extends ValueDateTime<T>
 */
final class ValueDate extends ValueDateTime
{
    protected function defaultFormat(Configuration $configuration): string
    {
        return 'Y-m-d';
    }
}
