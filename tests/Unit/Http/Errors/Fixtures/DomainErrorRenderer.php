<?php

declare(strict_types=1);

namespace Test\Unit\Http\Errors\Fixtures;

use Ark4ne\JsonApi\Http\Errors\ErrorDocument;
use Ark4ne\JsonApi\Http\Errors\ErrorObject;
use Ark4ne\JsonApi\Http\Errors\ErrorRenderer;
use Illuminate\Http\Request;
use LogicException;
use Throwable;

/**
 * A renderer of the application: a `LogicException` is a conflict.
 */
final readonly class DomainErrorRenderer implements ErrorRenderer
{
    public const PRIORITY = 1000;

    public function supports(Throwable $exception): bool
    {
        return $exception instanceof LogicException;
    }

    public function render(Throwable $exception, Request $request): ErrorDocument
    {
        return new ErrorDocument(409, [new ErrorObject(status: 409, detail: $exception->getMessage())]);
    }
}
