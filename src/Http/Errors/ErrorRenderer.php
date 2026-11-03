<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Http\Errors;

use Illuminate\Http\Request;
use Throwable;

/**
 * Renders the exceptions of a family as a document of errors (pattern Chain of Responsibility, docs/architecture.md
 * §10). `ErrorRenderers` asks the renderers by decreasing `PRIORITY`: the first that supports the exception renders it.
 *
 * Laravel's exception handler gives its render callbacks the exception it prepared: a `ModelNotFoundException` or an
 * `AuthorizationException` arrives as the `HttpException` it became, holding it as its previous exception.
 */
interface ErrorRenderer
{
    /** The higher, the sooner the renderer is asked */
    public const PRIORITY = 0;

    public function supports(Throwable $exception): bool;

    public function render(Throwable $exception, Request $request): ErrorDocument;
}
