<?php

namespace Ark4ne\JsonApi\Filters;

use Closure;
use Illuminate\Http\Request;

/**
 * @template Resource
 *
 * @implements FilterRule<Resource>
 */
class CallbackFilterRule implements FilterRule
{
    /**
     * @param Closure(Request, Resource): bool $callback
     */
    public function __construct(
        protected Closure $callback
    ) {
    }

    /**
     * @param Request $request
     * @param Resource $model
     * @return bool
     */
    public function passes(Request $request, mixed $model): bool
    {
        return (bool) ($this->callback)($request, $model);
    }
}