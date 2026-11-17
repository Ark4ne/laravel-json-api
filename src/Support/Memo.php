<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Support;

use Closure;

/**
 * Memoizes pure computations whose result only depends on the code (the order of the cases of an enum): computed once,
 * then read. The second static state of the package, after `Compilation\Definitions` (docs/architecture.md §4).
 *
 * Never holds anything that depends on a request, a user or the configuration. Long-running workers (Octane) flush it
 * after every operation (`JsonApiServiceProvider`), so nothing outlives a request even so.
 *
 * Laravel's `once()` is not used: it keys its values by a backtrace on every call, costlier on the rendering paths than
 * what it would save.
 */
final class Memo
{
    /** @var array<string, array<array-key, mixed>> By scope, then key */
    private static array $values = [];

    /**
     * The value of `$key` in `$scope`, computed by `$compute` the first time.
     *
     * @template T
     *
     * @param Closure(): T $compute
     *
     * @return T
     */
    public static function remember(string $scope, int|string $key, Closure $compute): mixed
    {
        if (isset(self::$values[$scope]) && array_key_exists($key, self::$values[$scope])) {
            /** @var T */
            return self::$values[$scope][$key];
        }

        return self::$values[$scope][$key] = $compute();
    }

    /**
     * Forgets the values of `$scope`, of every scope when `null`.
     */
    public static function flush(?string $scope = null): void
    {
        if ($scope === null) {
            self::$values = [];
        } else {
            unset(self::$values[$scope]);
        }
    }
}
