<?php

namespace Ark4ne\JsonApi\Resources\Concerns;

use Illuminate\Http\Request;

trait Meta
{
    /**
     * @see https://jsonapi.org/format/#document-resource-objects
     * @see https://jsonapi.org/format/#document-meta
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return iterable<string, mixed>|null
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
     * @return iterable<string, string>|null
     */
    protected function toMeta(Request $request): ?iterable
    {
        return null;
    }
}
