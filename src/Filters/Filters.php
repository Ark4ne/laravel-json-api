<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Filters;

use Ark4ne\JsonApi\Support\Values;
use Closure;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\MissingValue;
use Illuminate\Support\Collection;

/**
 * The rules a related resource must pass to be rendered in a relationship, all of them (`->filters()`).
 *
 * Immutable: `can()` and `when()` return a copy with the rule added.
 */
final class Filters
{
    /** @var list<FilterRule<mixed>> */
    protected array $rules = [];

    /**
     * Add a policy-based filter
     *
     * @param iterable<string>|string $abilities Abilities to check
     * @param array<mixed> $arguments Arguments to pass to the policy method, the model is always the first argument
     * @param string $gateClass Gate class to use, defaults to the default Gate implementation
     * @param string|null $guard Guard to use, defaults to the default guard
     * @return static
     */
    public function can(iterable|string $abilities, array $arguments = [], string $gateClass = Gate::class, ?string $guard = null): static
    {
        return $this->with(new PolicyFilterRule($abilities, $arguments, $gateClass, $guard));
    }

    /**
     * Add a custom filter rule
     *
     * @template TFiltered The related model the callback is written for (`fn(Request $request, Post $post) => …`)
     *
     * @param Closure(Request, TFiltered): mixed $callback Receives the request and the model, keeps the model when it
     *                                                     returns a truthy value
     * @return static
     */
    public function when(Closure $callback): static
    {
        return $this->with(new CallbackFilterRule($callback));
    }

    /**
     * @param FilterRule<mixed> $rule
     */
    protected function with(FilterRule $rule): static
    {
        $filters = clone $this;
        $filters->rules[] = $rule;

        return $filters;
    }

    /**
     * Apply all filters to the given data: the related value of a relationship, read once its condition passed.
     *
     * @param mixed $data `null`, a missing value, a model or an iterable of models
     *
     * @return mixed The data, the models of an iterable that pass (a `Collection` stays one), a `MissingValue` for a
     *               model that does not
     */
    public function apply(Request $request, mixed $data): mixed
    {
        if ($data === null || Values::isMissing($data)) {
            return $data;
        }

        if ($data instanceof Collection) {
            return $data->filter(fn(mixed $item): bool => $this->shouldInclude($request, $item));
        }

        if (is_iterable($data)) {
            return array_filter(
                is_array($data) ? $data : iterator_to_array($data),
                fn(mixed $item): bool => $this->shouldInclude($request, $item),
            );
        }

        return $this->shouldInclude($request, $data) ? $data : new MissingValue();
    }

    /**
     * Whether a model passes every rule: the rules were declared for the models of the relationship.
     */
    private function shouldInclude(Request $request, mixed $model): bool
    {
        foreach ($this->rules as $rule) {
            if (!$rule->passes($request, $model)) {
                return false;
            }
        }

        return true;
    }
}
