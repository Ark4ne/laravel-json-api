<?php

declare(strict_types=1);

namespace Test\Unit\Compilation\Fixtures;

use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;

/**
 * @extends JsonApiResource<\Test\app\Models\Post>
 */
final class MergeWhenResource extends JsonApiResource
{
    protected function toType(Request $request): string
    {
        return 'merge-when';
    }

    public function toAttributes(Request $request): iterable
    {
        return [
            'title' => $this->string(),
            $this->mergeWhen(false, [
                'hidden' => $this->string(),
            ]),
        ];
    }
}
