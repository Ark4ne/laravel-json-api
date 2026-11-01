<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Http\Errors;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Throwable;

/**
 * An `AuthenticationException`: `401`, its message as detail.
 */
final readonly class AuthenticationErrorRenderer implements ErrorRenderer
{
    public const PRIORITY = 300;

    public function supports(Throwable $exception): bool
    {
        return $exception instanceof AuthenticationException;
    }

    public function render(Throwable $exception, Request $request): ErrorDocument
    {
        return new ErrorDocument(401, [
            new ErrorObject(status: 401, title: ErrorObject::titleOf(401), detail: $exception->getMessage()),
        ]);
    }
}
