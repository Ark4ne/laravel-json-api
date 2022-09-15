<?php

namespace Ark4ne\JsonApi\Schema;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

/**
 * @template T as JsonApiSchema
 */
abstract class JsonApiSchemaCollection implements Responsable
{
    public function __construct(
        public Collection $collection
    ) {
    }

    public function toResponse($request): JsonResponse|Response
    {
        return JsonApiSchemaResource
            ::collection($this->collection->mapInto($this->for()))
            ->toResponse($request);
    }

    /**
     * @return class-string<T>
     */
    abstract public function for(): string;
}
