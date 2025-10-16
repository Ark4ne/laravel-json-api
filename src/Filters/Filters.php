<?php

namespace Ark4ne\JsonApi\Filters;

use Ark4ne\JsonApi\Support\Values;
use Closure;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\MissingValue;
use Illuminate\Http\Resources\PotentiallyMissing;
use Illuminate\Support\Collection;

/**
 * @template Resource
 */
class Filters
{
    /** @var array<FilterRule<Resource>> */
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
        $this->rules[] = new PolicyFilterRule($abilities, $arguments, $gateClass, $guard);
        return $this;
    }

    /**
     * Add a custom filter rule
     *
     * @param Closure(Request, Resource): bool $callback Callback that receives (Request $request, Model $model) and returns bool
     * @return static
     */
    public function when(Closure $callback): static
    {
        $this->rules[] = new CallbackFilterRule($callback);
        return $this;
    }

    /**
     * Apply all filters to the given data
     *
     * @param Request $request
     * @param null|PotentiallyMissing|Resource|iterable<array-key, Resource> $data
     * @return mixed
     */
    public function apply(Request $request, mixed $data): mixed
    {
        if ($data === null) {
            return $data;
        }
        if (Values::isMissing($data)) {
            return $data;
        }

        // If it's a collection/array, filter each item
        if (is_iterable($data)) {
            $filtered = (new Collection($data))
                ->filter(fn($item) => $this->shouldInclude($request, $item));

            // Preserve the original collection type
            if ($data instanceof Collection) {
                return $filtered;
            }
            
            return $filtered->all();
        }

        // Single model - check if it should be included
        return $this->shouldInclude($request, $data)
            ? $data
            : new MissingValue();
    }

    /**
     * Check if a model should be included based on all filter rules
     *
     * @param Request $request
     * @param Resource $model
     * @return bool
     */
    protected function shouldInclude(Request $request, mixed $model): bool
    {
        // All rules must pass
        foreach ($this->rules as $rule) {
            if (!$rule->passes($request, $model)) {
                return false;
            }
        }

        return true;
    }
}