<?php

namespace Ark4ne\JsonApi\Resources\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

trait Identifier
{
    /**
     * @see https://jsonapi.org/format/#document-resource-object-identification
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return string
     *
     * ```
     * return 'post'; // resource type name
     * ```
     */
    protected function toType(Request $request): string
    {
        return str_contains($this::class, '@anonymous')
            ? 'anonymous'
            : Str::kebab(Str::beforeLast(Str::afterLast($this::class, "\\"), 'Resource'));
    }

    /**
     * @see https://jsonapi.org/format/#document-resource-object-identification
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return int|string
     *
     * ```
     * return $this->id; // resource identifier
     * ```
     */
    protected function toIdentifier(Request $request): int|string
    {
        if ($this->resource instanceof Model) {
            return $this->resource->getKey();
        }

        return $this->resource->id;
    }

}
