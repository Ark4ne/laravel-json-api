<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Filters;

use Illuminate\Http\Request;

/**
 * @template TModel
 */
interface FilterRule
{
    /**
     * Determine if the filter rule passes for the given model
     *
     * @param Request $request
     * @param TModel $model The model being filtered
     * @return bool
     */
    public function passes(Request $request, mixed $model): bool;
}
