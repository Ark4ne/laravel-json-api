<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Http\Errors;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Throwable;

/**
 * Any other exception: `500`. In debug, its message as detail and its class, file and line in `meta`; never otherwise.
 * An `HttpResponseException` is left to Laravel, which sends its response.
 */
final readonly class ThrowableErrorRenderer implements ErrorRenderer
{
    public const PRIORITY = 0;

    public function __construct(
        private bool $debug = false,
    ) {}

    public function supports(Throwable $exception): bool
    {
        return !$exception instanceof HttpResponseException;
    }

    public function render(Throwable $exception, Request $request): ErrorDocument
    {
        return new ErrorDocument(500, [new ErrorObject(
            status: 500,
            title: ErrorObject::titleOf(500),
            detail: $this->debug ? $exception->getMessage() : null,
            meta: $this->debug
                ? ['exception' => $exception::class, 'file' => $exception->getFile(), 'line' => $exception->getLine()]
                : [],
        )]);
    }
}
