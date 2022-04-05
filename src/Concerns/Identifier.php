<?php

namespace Ark4ne\JsonApi\Resource\Concerns;

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
        return Str::kebab(Str::afterLast($this->resource::class, "\\"));
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
        return $this->id;
    }

}
