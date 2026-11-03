<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Filters;

use Ark4ne\JsonApi\Support\Gates;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\Request;

/**
 * @template TModel
 *
 * @implements FilterRule<TModel>
 */
final class PolicyFilterRule implements FilterRule
{
    /**
     * @param iterable<string>|string $abilities
     * @param array<mixed> $arguments
     */
    public function __construct(
        protected iterable|string $abilities,
        protected array $arguments = [],
        protected string $gateClass = Gate::class,
        protected ?string $guard = null,
    ) {}

    /**
     * @param Request $request
     * @param TModel $model
     * @return bool
     */
    public function passes(Request $request, mixed $model): bool
    {
        return Gates::resolve($this->gateClass)
            ->forUser($request->user($this->guard))
            ->allows($this->abilities, [$model, ...$this->arguments]);
    }
}
