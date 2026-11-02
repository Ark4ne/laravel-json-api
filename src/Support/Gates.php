<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Support;

use Ark4ne\JsonApi\Exceptions\DefinitionException;
use Illuminate\Contracts\Auth\Access\Gate;

/**
 * The gate an authorization rule (`can()`, `Filters::can()`) checks against. The only container access outside
 * `src/Laravel/`: the rules are closures run on rendering, which receive the request but not the context.
 *
 * @internal
 */
final class Gates
{
    /**
     * @param string $class The gate class, `Gate::class` for the application's
     *
     * @throws DefinitionException when the class does not implement Laravel's `Gate` contract
     */
    public static function resolve(string $class = Gate::class): Gate
    {
        $gate = app($class); // @phpstan-ignore disallowed.function (rules receive the request only, not the context)

        return $gate instanceof Gate ? $gate : throw DefinitionException::notAGate($class);
    }
}
