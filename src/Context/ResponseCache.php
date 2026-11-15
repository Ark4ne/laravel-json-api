<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Context;

use Closure;

/**
 * What is built once for a response and read for each of its resources (the plans of its values): shared by every
 * context derived from the one of the request, gone with it. Never static: nothing outlives a response.
 */
final class ResponseCache
{
    /** @var array<string, mixed> */
    private array $entries = [];

    /**
     * The entry `$key`, built by `$build` the first time.
     *
     * @template T
     *
     * @param Closure(): T $build
     *
     * @return T
     */
    public function remember(string $key, Closure $build): mixed
    {
        if (!array_key_exists($key, $this->entries)) {
            $this->entries[$key] = $build();
        }

        /** @var T */
        return $this->entries[$key];
    }
}
