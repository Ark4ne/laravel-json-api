<?php

declare(strict_types=1);

namespace Test\Feature\Loading\Fixtures;

use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;

/**
 * A polymorphic relationship without any resource: refused.
 *
 * @extends JsonApiResource<mixed>
 */
final class EmptyMorphResource extends JsonApiResource
{
    protected function toType(Request $request): string
    {
        return 'empty';
    }

    /**
     * @return iterable<string, \Ark4ne\JsonApi\Descriptors\Relations\Relation<mixed>>
     */
    protected function toRelationships(Request $request): iterable
    {
        return ['likeable' => $this->morph([])];
    }
}
