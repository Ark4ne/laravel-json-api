<?php

namespace Ark4ne\JsonApi\Filters;

use Illuminate\Http\Request;

/**
 * @template Resource
 */
interface FilterRule
{
    /**
     * Determine if the filter rule passes for the given model
     *
     * @param Request $request
     * @param Resource $model The model being filtered
     * @return bool
     */
    public function passes(Request $request, mixed $model): bool;
}