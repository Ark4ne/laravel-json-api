<?php

namespace Ark4ne\JsonApi\Resources\Concerns;

use Ark4ne\JsonApi\Support\Fields;
use Ark4ne\JsonApi\Support\Includes;
use Illuminate\Http\Request;

trait ConditionallyLoadsAttributes
{
    /**
     * Retrieve a relationship if it has been present in fields.
     *
     * @template T
     *
     * @param \Illuminate\Http\Request $request
     * @param string                   $attribute
     * @param T                        $value
     *
     * @return \Illuminate\Http\Resources\MissingValue|T
     */
    protected function whenInFields(Request $request, string $attribute, mixed $value)
    {
        return $this->when(in_array($attribute, Fields::get($request, $this->toType($request)) ?? [], true), $value);
    }

    /**
     * Retrieve a relationship if it has been included.
     *
     * @template T
     *
     * @param \Illuminate\Http\Request $request
     * @param string                   $type
     * @param T                        $value
     *
     * @return \Illuminate\Http\Resources\MissingValue|T
     */
    protected function whenIncluded(Request $request, string $type, mixed $value)
    {
        return $this->when(Includes::include($request, $type), $value);
    }
}
