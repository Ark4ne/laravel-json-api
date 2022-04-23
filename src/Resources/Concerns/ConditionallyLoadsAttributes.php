<?php

namespace Ark4ne\JsonApi\Resources\Concerns;

use Ark4ne\JsonApi\Support\Includes;
use Illuminate\Http\Request;

trait ConditionallyLoadsAttributes
{
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
