<?php

declare(strict_types=1);

namespace Test\Feature\Laravel\Fixtures;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * An exception rendering itself: Laravel calls `render()` before any callback.
 */
final class RenderableException extends RuntimeException
{
    public function render(): JsonResponse
    {
        return new JsonResponse(['rendered' => 'itself'], 418);
    }
}
