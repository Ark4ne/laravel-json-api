<?php

declare(strict_types=1);

namespace Test\Unit\Http\Errors\Fixtures;

use Ark4ne\JsonApi\Http\Errors\ErrorDocument;
use Ark4ne\JsonApi\Http\Errors\ErrorObject;
use Ark4ne\JsonApi\Http\Errors\ErrorRenderer;
use Ark4ne\JsonApi\Http\Errors\HttpErrorRenderer;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Replaces the rendering of the package for the `404`s only: they are `410` in this application.
 */
final readonly class NotFoundErrorRenderer implements ErrorRenderer
{
    public const PRIORITY = HttpErrorRenderer::PRIORITY + 1;

    public function supports(Throwable $exception): bool
    {
        return $exception instanceof NotFoundHttpException;
    }

    public function render(Throwable $exception, Request $request): ErrorDocument
    {
        return new ErrorDocument(410, [new ErrorObject(status: 410, title: ErrorObject::titleOf(410))]);
    }
}
