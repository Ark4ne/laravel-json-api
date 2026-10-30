<?php

declare(strict_types=1);

namespace Test\Unit\Compilation\Fixtures;

use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;

/**
 * Declares different attributes on each run, as a hook branching on the request would.
 *
 * @extends JsonApiResource<\Test\app\Models\Post>
 */
final class RequestDependentResource extends JsonApiResource
{
    public static int $runs = 0;

    protected function toType(Request $request): string
    {
        return 'request-dependent';
    }

    public function toAttributes(Request $request): iterable
    {
        return ++self::$runs % 2 === 1
            ? ['title' => $this->string()]
            : ['title' => $this->string(), 'admin-only' => $this->string()];
    }
}
