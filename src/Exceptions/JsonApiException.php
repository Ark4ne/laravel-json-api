<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Exceptions;

use Throwable;

/**
 * Implemented by every exception of the package (docs/architecture.md §12).
 */
interface JsonApiException extends Throwable {}
