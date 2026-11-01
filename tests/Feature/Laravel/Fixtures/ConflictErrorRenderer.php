<?php

declare(strict_types=1);

namespace Test\Feature\Laravel\Fixtures;

use Ark4ne\JsonApi\Http\Errors\ErrorDocument;
use Ark4ne\JsonApi\Http\Errors\ErrorObject;
use Ark4ne\JsonApi\Http\Errors\ErrorRenderer;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

/**
 * A renderer of the application with a dependency, built by the container: a `RuntimeException` is a conflict.
 */
final readonly class ConflictErrorRenderer implements ErrorRenderer
{
    public const PRIORITY = 1000;

    public function __construct(
        private string $detail,
    ) {}

    public function supports(Throwable $exception): bool
    {
        return $exception instanceof RuntimeException;
    }

    public function render(Throwable $exception, Request $request): ErrorDocument
    {
        return new ErrorDocument(409, [new ErrorObject(status: 409, title: ErrorObject::titleOf(409), detail: $this->detail)]);
    }
}
