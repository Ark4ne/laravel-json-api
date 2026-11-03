<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Http\Errors;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * An `AuthorizationException`, as is or as the `HttpException` Laravel made of it: its status (`403`, or the one of
 * `denyWithStatus()` / `denyAsNotFound()`), its message as detail; with a status, only the message of its response,
 * as Laravel prepared it.
 */
final readonly class AuthorizationErrorRenderer implements ErrorRenderer
{
    public const PRIORITY = 300;

    public function supports(Throwable $exception): bool
    {
        return PreparedException::unwrap($exception, AuthorizationException::class) !== null;
    }

    public function render(Throwable $exception, Request $request): ErrorDocument
    {
        $authorization = PreparedException::unwrap($exception, AuthorizationException::class);
        $status = match (true) {
            $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
            $authorization !== null && $authorization->hasStatus() => (int) $authorization->status(),
            default => 403,
        };
        // A denial with a status (`denyAsNotFound()` hiding the resource) has the message Laravel prepared of its
        // response only, never the default message of the exception.
        $detail = match (true) {
            $authorization === null => null,
            !$authorization->hasStatus() => $authorization->getMessage(),
            $exception instanceof HttpExceptionInterface && $exception->getMessage() !== ErrorObject::titleOf($status)
                => $exception->getMessage(),
            default => null,
        };

        return new ErrorDocument(
            $status,
            [new ErrorObject(status: $status, title: ErrorObject::titleOf($status), detail: $detail)],
            PreparedException::headers($exception),
        );
    }
}
