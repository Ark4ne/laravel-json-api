<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Laravel;

use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Http\Errors\ErrorRenderers;
use Closure;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * The entry point of an application into the package, called from its service providers.
 */
final class JsonApi
{
    private function __construct() {}

    /**
     * Renders the exceptions as JSON:API error documents, through the `ErrorRenderers` of the container, for the
     * requests that expect JSON (`$request->expectsJson()`) or those `$when` accepts. The one line of
     * `bootstrap/app.php`:
     *
     * ```php
     * ->withExceptions(function (Exceptions $exceptions) {
     *     JsonApi::renderErrors($exceptions);
     * })
     * ```
     *
     * An exception no renderer supports, or a request left out, is rendered by Laravel; so is an exception rendering
     * itself (`render()`, `Responsable`), before any callback.
     *
     * Laravel runs its render callbacks in the order they are registered, and `ThrowableErrorRenderer` supports every
     * exception: call it **after** the `render()` callbacks of the application, which would never run otherwise.
     *
     * `$when` and the renderers receive the exception Laravel prepared: a `ModelNotFoundException` is a
     * `NotFoundHttpException` then, an `AuthorizationException` an `AccessDeniedHttpException` (the original being its
     * previous exception). `$exceptions->shouldRenderJsonWhen()` is not read: give the same condition to `$when`.
     *
     * @param (Closure(Request, Throwable): bool)|null $when
     */
    public static function renderErrors(Exceptions $exceptions, ?Closure $when = null): void
    {
        $exceptions->render(static function (Throwable $exception, Request $request) use ($when): ?JsonResponse {
            if (!($when === null ? $request->expectsJson() : $when($request, $exception))) {
                return null;
            }

            try {
                /** @var ErrorRenderers $renderers */
                $renderers = app(ErrorRenderers::class);
            } catch (Throwable) {
                // An invalid configuration must not hide the exception being rendered: Laravel renders it.
                return null;
            }

            return $renderers->render($exception, $request)?->toResponse();
        });
    }
}
