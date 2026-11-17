<?php

declare(strict_types=1);

namespace Test\Feature\Laravel\Fixtures\Probed;

use Ark4ne\JsonApi\Support\Probe;

/**
 * Counts the hooks of the probed resources run on a probe standing for their model.
 */
final class ProbedHooks
{
    /** @var list<string> */
    private static array $runs = [];

    public static function record(string $hook, mixed $resource): void
    {
        if ($resource instanceof Probe) {
            self::$runs[] = $hook;
        }
    }

    /**
     * The hooks run on a probe since the last call, as `Class::hook`.
     *
     * @return list<string>
     */
    public static function runs(): array
    {
        $runs = self::$runs;
        self::$runs = [];

        return $runs;
    }
}
