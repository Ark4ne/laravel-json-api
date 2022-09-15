<?php

namespace Ark4ne\JsonApi\Resources\Concerns;

use Ark4ne\JsonApi\Descriptors\Resolver;
use Illuminate\Http\Request;

trait Meta
{
    use Resolver;

    /**
     * @see https://jsonapi.org/format/#document-resource-objects
     * @see https://jsonapi.org/format/#document-meta
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return iterable<string, mixed>|iterable<array-key, \Ark4ne\JsonApi\Descriptors\Values\Value>|null
     *
     * ```
     * return [
     *     'created_at' => $this->created_at->format(DateTimeInterface::ATOM),
     *     'updated_at' => $this->updated_at->format(DateTimeInterface::ATOM),
     * ];
     * ```
     */
    protected function toResourceMeta(Request $request): ?iterable
    {
        return null;
    }

    /**
     * @see https://jsonapi.org/format/#document-meta
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return iterable<string, mixed>|iterable<array-key, \Ark4ne\JsonApi\Descriptors\Values\Value>|null
     */
    protected function toMeta(Request $request): ?iterable
    {
        return null;
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array<string, mixed>|null
     */
    private function requestedResourceMeta(Request $request): ?array
    {
        return $this->resolveValues($request, $this->toResourceMeta($request));
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array<string, mixed>|null
     */
    private function requestedMeta(Request $request): ?array
    {
        return $this->resolveValues($request, $this->toMeta($request));
    }
}
