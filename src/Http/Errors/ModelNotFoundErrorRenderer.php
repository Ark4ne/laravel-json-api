<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Http\Errors;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Throwable;

/**
 * A `ModelNotFoundException`, as is or as the `NotFoundHttpException` Laravel made of it: `404`, without detail (its
 * message names the class of the model).
 */
final readonly class ModelNotFoundErrorRenderer implements ErrorRenderer
{
    public const PRIORITY = 300;

    public function supports(Throwable $exception): bool
    {
        return PreparedException::unwrap($exception, ModelNotFoundException::class) !== null;
    }

    public function render(Throwable $exception, Request $request): ErrorDocument
    {
        return new ErrorDocument(
            404,
            [new ErrorObject(status: 404, title: ErrorObject::titleOf(404))],
            PreparedException::headers($exception),
        );
    }
}
