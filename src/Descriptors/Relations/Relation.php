<?php

namespace Ark4ne\JsonApi\Descriptors\Relations;

use Ark4ne\JsonApi\Descriptors\Describer;
use Ark4ne\JsonApi\Resources\Relationship;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\MissingValue;

/**
 * @template T as \Illuminate\Database\Eloquent\Model
 * @extends Describer<T>
 */
abstract class Relation extends Describer
{
    protected ?Closure $links = null;
    protected ?Closure $meta = null;
    protected bool $whenIncluded = false;

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

    public function whenIncluded(): static
    {
        $this->whenIncluded = true;
        return $this;
    }

    /**
     * @see \Illuminate\Http\Resources\ConditionallyLoadsAttributes::whenLoaded
     *
     * @param string|null $relation
     *
     * @return $this
     */
    public function whenLoaded(string $relation = null): static
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
     * @return $this
     */
    public function whenPivotLoaded(string $table, string $accessor = null): static
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

    public function resolveFor(Request $request, Model $model, string $field): Relationship
    {
        $retriever = $this->retriever();

        if ($retriever instanceof Closure) {
            $value = static fn() => $retriever($model, $field);
        } else {
            $value = static fn() => $model->getRelationValue($retriever ?? $field);
        }

        $relation = $this->value(fn() => $this->check($request, $model, $field) ? $value() : new MissingValue());

        if ($this->whenIncluded) {
            $relation->whenIncluded();
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
    public function valueFor(Request $request, Model $model, string $field): mixed
    {
        return $this->resolveFor($request, $model, $field);
    }

    abstract protected function value(Closure $value): Relationship;
}
