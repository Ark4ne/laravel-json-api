<?php

declare(strict_types=1);

namespace Test\Unit\Laravel\Fixtures;

/**
 * Extends a class that is not installed (a dev-only dependency in production): loading it throws. Excluded from
 * PHPStan.
 */
final class BrokenResource extends \Missing\DevDependency {}
