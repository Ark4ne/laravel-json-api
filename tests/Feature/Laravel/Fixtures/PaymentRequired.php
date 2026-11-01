<?php

declare(strict_types=1);

namespace Test\Feature\Laravel\Fixtures;

use RuntimeException;

/**
 * An exception of the application, rendered by a callback of its own.
 */
final class PaymentRequired extends RuntimeException {}
