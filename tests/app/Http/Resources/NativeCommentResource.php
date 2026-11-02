<?php

declare(strict_types=1);

namespace Test\app\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/**
 * A comment of Laravel's native JSON:API, related to by `NativePostResource`.
 */
final class NativeCommentResource extends JsonApiResource
{
    public function toType(Request $request): string
    {
        return 'comments';
    }

    /**
     * @return list<string>
     */
    public function toAttributes(Request $request): array
    {
        return ['content'];
    }
}
