<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Descriptors\Relations;

use Ark4ne\JsonApi\Definition\DefinitionSource;
use Ark4ne\JsonApi\Definition\RelationshipDefinition;
use Ark4ne\JsonApi\Descriptors\Describer;
use Ark4ne\JsonApi\Exceptions\DefinitionException;
use Ark4ne\JsonApi\Filters\Filters;
use Ark4ne\JsonApi\Resources\Relationship;
use Ark4ne\JsonApi\Traits\HasRelationLoad;
use Closure;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\MissingValue;
use Illuminate\Support\Arr;
use ReflectionClass;

/**
 * @template T
 * @extends Describer<T>
 */
abstract class Relation extends Describer
{
    use HasRelationLoad;

    protected ?Closure $links = null;
    protected ?Closure $meta = null;
    protected ?bool $whenIncluded = null;
    protected ?Filters $filters = null;

    /**
     * @param class-string<\Ark4ne\JsonApi\Resources\JsonApiResource|\Ark4ne\JsonApi\Resources\JsonApiCollection> $related
     * @param string|Closure|null $relation
     */
    public function __construct(
        protected string              $related,
        protected string|Closure|null $relation,
    ) {}

    /**
     * @return class-string<\Ark4ne\JsonApi\Resources\JsonApiResource|\Ark4ne\JsonApi\Resources\JsonApiCollection>
     */
    public function related(): string
    {
        return $this->related;
    }

    /**
     * @return null|string|Closure
     */
    public function retriever(): string|Closure|null
    {
        return $this->relation;
    }

    public function links(Closure $links): static
    {
        $this->links = $links;
        return $this;
    }

    public function meta(Closure $meta): static
    {
        $this->meta = $meta;
        return $this;
    }

    /**
     * Set filters for the relationship
     *
     * @param Closure(Filters): Filters $filters Callback that receives (Filters $filters) and configures the filters
     * @return static
     */
    public function filters(Closure $filters): static
    {
        $this->filters = $filters(new Filters());
        return $this;
    }

    /**
     * @param iterable<mixed>|string $abilities Abilities to check
     * @param array<mixed> $arguments Arguments to pass to the policy method, the model is always the first argument
     * @param string $gateClass Gate class to use, defaults to the default Gate implementation
     * @param string|null $guard Guard to use, defaults to the default guard
     * @return static
     */
    public function can(iterable|string $abilities, array $arguments = [], string $gateClass = Gate::class, ?string $guard = null): static
    {
        return $this->when(
            fn(
                Request $request,
                Model   $model,
                string  $attribute,
            ) => app($gateClass)
            ->forUser($request->user($guard))
            ->allows($abilities, [$model, ...$arguments]),
        );
    }

    /**
     * @param bool|null $whenIncluded
     * @return static
     */
    public function whenIncluded(?bool $whenIncluded = null): static
    {
        if ($whenIncluded === null) {
            $this->whenIncluded ??= true;
        } else {
            $this->whenIncluded = $whenIncluded;
        }

        return $this;
    }

    /**
     * @param string|null $relation
     *
     * @return static
     * @see \Illuminate\Http\Resources\ConditionallyLoadsAttributes::whenLoaded
     */
    public function whenLoaded(?string $relation = null): static
    {
        return $this->when(fn(
            Request $request,
            Model   $model,
            string  $attribute,
        ): bool => $model->relationLoaded($relation ?? (is_string($this->relation) ? $this->relation : $attribute)));
    }

    /**
     * @param string $table
     * @param string|null $accessor
     *
     * @return static
     * @see \Illuminate\Http\Resources\ConditionallyLoadsAttributes::whenPivotLoadedAs
     */
    public function whenPivotLoaded(string $table, ?string $accessor = null): static
    {
        return $this->when(
            fn(
                Request $request,
                Model   $model,
                string  $attribute,
            ): bool => ($pivot = $model->{$accessor ?? (is_string($this->relation) ? $this->relation : $attribute)})
            && (
                $pivot instanceof $table
                || $pivot->getTable() === $table
            ),
        );
    }

    public function resolveFor(Request $request, mixed $model, string $attribute): Relationship
    {
        $retriever = $this->retriever();

        if ($retriever instanceof Closure) {
            $value = static fn() => $retriever($model, $attribute);
        } else {
            $value = static fn() => match (true) {
                $model instanceof Model => $model->getRelationValue($retriever ?? $attribute),
                Arr::accessible($model) => $model[$retriever ?? $attribute],
                default => $model->{$retriever ?? $attribute},
            };
        }

        $relation = $this->value(fn() => $this->check($request, $model, $attribute) ? $value() : new MissingValue());

        if ($this->whenIncluded !== null) {
            $relation->whenIncluded($this->whenIncluded);
        }

        if ($this->filters !== null) {
            $relation->withFilters($this->filters);
        }

        return $relation;
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param T $model
     * @param string $attribute
     *
     * @return mixed
     */
    public function valueFor(Request $request, mixed $model, string $attribute): mixed
    {
        return $this->resolveFor($request, $model, $attribute);
    }

    /**
     * What this descriptor declares under a name: related resource, cardinality, eager-load, conditions, links and meta
     * (docs/architecture.md §3).
     */
    public function definition(string $name): RelationshipDefinition
    {
        return RelationshipDefinition::one($name, $this->relatedResource());
    }

    /**
     * The resource class of the related resources: the related class itself, or the class a related collection
     * collects (`$collects`).
     *
     * @return class-string<DefinitionSource>
     *
     * @throws DefinitionException when neither is a resource
     */
    protected function relatedResource(): string
    {
        $related = $this->related;

        if (!is_subclass_of($related, DefinitionSource::class)) {
            $collects = (new ReflectionClass($related))->getDefaultProperties()['collects'] ?? null;
            $related = is_string($collects) ? $collects : $related;
        }

        if (!is_subclass_of($related, DefinitionSource::class)) {
            throw DefinitionException::notADefinitionSource($related);
        }

        return $related;
    }

    abstract protected function value(Closure $value): Relationship;
}
