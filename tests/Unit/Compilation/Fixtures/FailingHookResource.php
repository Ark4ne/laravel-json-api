<?php

declare(strict_types=1);

namespace Test\Unit\Compilation\Fixtures;

use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * @extends JsonApiResource<\Test\app\Models\Post>
 */
final class FailingHookResource extends JsonApiResource
{
    protected function toType(Request $request): string
    {
        return 'failing-hook';
    }

    public function toAttributes(Request $request): iterable
    {
        return [
            'title' => $this->string(),
        ];
    }

    /**
     * @return iterable<string, \Ark4ne\JsonApi\Descriptors\Relations\Relation<\Test\app\Models\Post>>
     */
    protected function toRelationships(Request $request): iterable
    {
        throw new RuntimeException('Needs a real model.');
    }
}
