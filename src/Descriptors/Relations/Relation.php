<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Descriptors\Relations;

use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Definition\Cardinality;
use Ark4ne\JsonApi\Definition\DescribesRelationship;
use Ark4ne\JsonApi\Definition\LoadDefinition;
use Ark4ne\JsonApi\Definition\RelatedResource;
use Ark4ne\JsonApi\Definition\RelationshipDefinition;
use Ark4ne\JsonApi\Descriptors\Describer;
use Ark4ne\JsonApi\Exceptions\DefinitionException;
use Ark4ne\JsonApi\Filters\Filters;
use Ark4ne\JsonApi\Resources\Relationship;
use Ark4ne\JsonApi\Support\Values;
use Ark4ne\JsonApi\Traits\HasRelationLoad;
use Closure;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation as EloquentRelation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\MissingValue;
use Illuminate\Support\Arr;

/**
 * @template T
 * @extends Describer<T>
 */
abstract class Relation extends Describer implements DescribesRelationship
{
    use HasRelationLoad;

    protected ?Closure $links = null;
    protected ?Closure $meta = null;
    protected ?Closure $identifierMeta = null;
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
        return $this->with('links', $links);
    }

    public function meta(Closure $meta): static
    {
        return $this->with('meta', $meta);
    }

    /**
     * Meta of each resource identifier of the linkage (`data.meta`, `data[].meta`): data of the link itself, such as the
     * pivot of a many-to-many relation. The closure receives each related resource; the meta of the relationship
     * object stay those of `meta()`.
     *
     * @see https://jsonapi.org/format/#document-resource-identifier-objects
     */
    public function identifierMeta(Closure $meta): static
    {
        return $this->with('identifierMeta', $meta);
    }

    /**
     * @param bool|string|array<string, callable(Builder|EloquentRelation):mixed>|array<array-key, string|string[]|mixed> $load
     */
    public function withLoad(bool|string|array $load): static
    {
        return $this->with('load', $load);
    }

    /**
     * Set filters for the relationship
     *
     * @param Closure(Filters): Filters $filters Callback that receives (Filters $filters) and configures the filters
     * @return static
     */
    public function filters(Closure $filters): static
    {
        return $this->with('filters', $filters(new Filters()));
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
        return $this->when(static function (Request $request, mixed $model, string $attribute) use (
            $abilities,
            $arguments,
            $gateClass,
            $guard,
        ): bool {
            $gate = app($gateClass);

            return $gate instanceof Gate
                ? $gate->forUser($request->user($guard))->allows($abilities, [$model, ...$arguments])
                : throw DefinitionException::notAGate($gateClass);
        });
    }

    /**
     * @param bool|null $whenIncluded
     * @return static
     */
    public function whenIncluded(?bool $whenIncluded = null): static
    {
        return $this->with('whenIncluded', $whenIncluded ?? $this->whenIncluded ?? true);
    }

    /**
     * Renders the relationship only when the relation is loaded: on a resource that is not an Eloquent model, when it
     * has the attribute.
     *
     * @param string|null $relation
     *
     * @return static
     * @see \Illuminate\Http\Resources\ConditionallyLoadsAttributes::whenLoaded
     */
    public function whenLoaded(?string $relation = null): static
    {
        return $this->when(function (Request $request, mixed $model, string $attribute) use ($relation): bool {
            $relation ??= is_string($this->relation) ? $this->relation : $attribute;

            return $model instanceof Model
                ? $model->relationLoaded($relation)
                : (is_array($model) || is_object($model)) && Values::hasAttribute($model, $relation);
        });
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
        return $this->when(function (Request $request, mixed $model, string $attribute) use ($table, $accessor): bool {
            $accessor ??= is_string($this->relation) ? $this->relation : $attribute;
            // A pivot is a relation of an Eloquent model, which hasAttribute() does not see.
            $pivot = match (true) {
                $model instanceof Model => $model->getAttribute($accessor),
                (is_array($model) || is_object($model)) && Values::hasAttribute($model, $accessor)
                    => Values::getAttribute($model, $accessor),
                default => null,
            };

            return $pivot instanceof $table || ($pivot instanceof Model && $pivot->getTable() === $table);
        });
    }

    public function resolveFor(
        Request $request,
        mixed $model,
        string $attribute,
        ?Configuration $configuration = null,
    ): Relationship {
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
    public function valueFor(
        Request $request,
        mixed $model,
        string $attribute,
        ?Configuration $configuration = null,
    ): mixed {
        return $this->resolveFor($request, $model, $attribute, $configuration);
    }

    /**
     * What this descriptor declares under a name: related resource, cardinality, eager-load, conditions, links and meta
     * (docs/architecture.md §3).
     */
    public function definition(string $name): RelationshipDefinition
    {
        $related = RelatedResource::resolve($this->related);
        $computed = $this->relation instanceof Closure;

        return ($this->cardinality() === Cardinality::Many
            ? RelationshipDefinition::many($name, $related)
            : RelationshipDefinition::one($name, $related))
            ->load(LoadDefinition::fromDeclaration($this->load, $name))
            ->conditional($this->isConditional())
            ->whenIncluded($this->whenIncluded)
            ->hasLinks($this->links !== null)
            ->hasMeta($this->meta !== null)
            ->hasIdentifierMeta($this->identifierMeta !== null)
            ->source($computed ? null : $this->relation ?? $name)
            ->computed($computed);
    }

    /**
     * @return array<string, Closure>
     */
    public function loadConstraints(): array
    {
        return LoadDefinition::constraintsOf($this->load);
    }

    /**
     * One related resource unless a subclass knows better.
     */
    protected function cardinality(): Cardinality
    {
        return Cardinality::One;
    }

    abstract protected function value(Closure $value): Relationship;
}
