<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Exceptions;

use InvalidArgumentException;

final class InvalidConfigurationException extends InvalidArgumentException implements JsonApiException
{
    /**
     * @param string $key Dotted key of `config/jsonapi.php` (`describer.precision`)
     * @param string $expected What the key accepts (`an integer or null`)
     */
    public static function forKey(string $key, string $expected): self
    {
        return new self("The configuration key [jsonapi.{$key}] must be {$expected}.");
    }

    /**
     * A key of 1.x renamed in 2.0, still set: its value would be ignored.
     */
    public static function renamed(string $key, string $now): self
    {
        return new self("The configuration key [jsonapi.{$key}] is renamed [jsonapi.{$now}].");
    }
}
