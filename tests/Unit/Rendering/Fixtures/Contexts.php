<?php

declare(strict_types=1);

namespace Test\Unit\Rendering\Fixtures;

use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Context\JsonApiContext;
use Ark4ne\JsonApi\Definition\Revision;
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

    /**
     * A context for a client on `$revision`.
     *
     * @param array<string, mixed> $config
     */
    public static function at(?Revision $revision, string $query = '', array $config = []): JsonApiContext
    {
        $request = Request::create("/?{$query}");
        $request->attributes->set(JsonApiContext::REVISION, $revision);

        return JsonApiContext::fromRequest($request, Configuration::fromArray($config));
    }
}
