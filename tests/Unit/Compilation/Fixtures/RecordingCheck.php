<?php

declare(strict_types=1);

namespace Test\Unit\Compilation\Fixtures;

use Ark4ne\ApiRevisions\Checking\Check;
use Ark4ne\ApiRevisions\Checking\CheckContext;

/**
 * Records the classes it is run on and whether the run is on a subset of the classes of the application.
 */
final class RecordingCheck implements Check
{
    /** @var list<string> */
    public static array $classes = [];

    public static ?bool $subset = null;

    public function run(CheckContext $context): void
    {
        if ($context->class === null) {
            self::$subset = $context->subset;

            return;
        }

        self::$classes[] = $context->class;
    }

    public static function reset(): void
    {
        self::$classes = [];
        self::$subset = null;
    }
}
