<?php

declare(strict_types=1);

namespace Test\Unit\Rendering\Fixtures;

use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Context\JsonApiContext;
use Illuminate\Http\Request;

/**
 * Contexts built from a query string.
 */
final class Contexts
{
    /**
     * @param array<string, mixed> $config
     */
    public static function of(string $query = '', array $config = []): JsonApiContext
    {
        return JsonApiContext::fromRequest(Request::create("/?{$query}"), Configuration::fromArray($config));
    }
}
