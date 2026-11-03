<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Http\Errors;

use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * An `HttpException` (`abort(404)`, `TokenMismatchException` made `419`…): its status and headers, its message as
 * detail when it has one.
 */
final readonly class HttpErrorRenderer implements ErrorRenderer
{
    public const PRIORITY = 200;

    public function supports(Throwable $exception): bool
    {
        return $exception instanceof HttpExceptionInterface;
    }

    public function render(Throwable $exception, Request $request): ErrorDocument
    {
        $status = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500;

        return new ErrorDocument($status, [
            new ErrorObject(status: $status, title: ErrorObject::titleOf($status), detail: $exception->getMessage()),
        ], PreparedException::headers($exception));
    }
}
