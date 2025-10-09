<?php

namespace Ark4ne\JsonApi\Filters;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\Request;

/**
 * @template Resource
 *
 * @implements FilterRule<Resource>
 */
class PolicyFilterRule implements FilterRule
{
    /**
     * @param iterable<string>|string $abilities
     * @param array<mixed> $arguments
     */
    public function __construct(
        protected iterable|string $abilities,
        protected array $arguments = [],
        protected string $gateClass = Gate::class,
        protected ?string $guard = null
    ) {
    }

    /**
     * @param Request $request
     * @param Resource $model
     * @return bool
     */
    public function passes(Request $request, mixed $model): bool
    {
        return app($this->gateClass)
            ->forUser($request->user($this->guard))
            ->allows($this->abilities, [$model, ...$this->arguments]);
    }
}