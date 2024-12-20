<?php

namespace Ark4ne\JsonApi\Descriptors\Relations;

use Ark4ne\JsonApi\Descriptors\Describer;
use Ark4ne\JsonApi\Resources\Relationship;
use Ark4ne\JsonApi\Traits\HasRelationLoad;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\MissingValue;
use Illuminate\Support\Arr;

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

    /**
     * @param class-string<\Ark4ne\JsonApi\Resources\JsonApiResource|\Ark4ne\JsonApi\Resources\JsonApiCollection> $related
     * @param string|\Closure|null                                                                                $relation
     */
    public function __construct(
        protected string $related,
        protected null|string|Closure $relation
    ) {
    }

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
    public function retriever(): null|string|Closure
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
     * @param bool|null $whenIncluded
     * @return $this
     */
    public function whenIncluded(null|bool $whenIncluded = null): static
    {
        if ($whenIncluded === null) {
            $this->whenIncluded ??= true;
        } else {
            $this->whenIncluded = $whenIncluded;
        }

        return $this;
    }

    /**
     * @see \Illuminate\Http\Resources\ConditionallyLoadsAttributes::whenLoaded
     *
     * @param string|null $relation
     *
     * @return static
     */
    public function whenLoaded(null|string $relation = null): self
    {
        return $this->when(fn(
            Request $request,
            Model $model,
            string $attribute
        ): bool => $model->relationLoaded($relation ?? (is_string($this->relation) ? $this->relation : $attribute)));
    }

    /**
     * @see \Illuminate\Http\Resources\ConditionallyLoadsAttributes::whenPivotLoadedAs
     *
     * @param string      $table
     * @param string|null $accessor
     *
     * @return static
     */
    public function whenPivotLoaded(string $table, null|string $accessor = null): self
    {
        return $this->when(fn(
            Request $request,
            Model $model,
            string $attribute
        ): bool => ($pivot = $model->{$accessor ?? (is_string($this->relation) ? $this->relation : $attribute)})
            && (
                $pivot instanceof $table ||
                $pivot->getTable() === $table
            )
        );
    }

    public function resolveFor(Request $request, mixed $model, string $field): Relationship
    {
        $retriever = $this->retriever();

        if ($retriever instanceof Closure) {
            $value = static fn() => $retriever($model, $field);
        } else {
            $value = static fn() => match (true) {
                $model instanceof Model => $model->getRelationValue($retriever ?? $field),
                Arr::accessible($model) => $model[$retriever ?? $field],
                default => $model->{$retriever ?? $field}
            };
        }

        $relation = $this->value(fn() => $this->check($request, $model, $field) ? $value() : new MissingValue());

        if ($this->whenIncluded !== null) {
            $relation->whenIncluded($this->whenIncluded);
        }

        return $relation;
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param T                        $model
     * @param string                   $field
     *
     * @return mixed
     */
    public function valueFor(Request $request, mixed $model, string $field): mixed
    {
        return $this->resolveFor($request, $model, $field);
    }

    abstract protected function value(Closure $value): Relationship;
}
