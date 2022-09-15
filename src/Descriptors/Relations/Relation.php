<?php

namespace Ark4ne\JsonApi\Descriptors\Relations;

use Ark4ne\JsonApi\Descriptors\Valuable;
use Ark4ne\JsonApi\Resources\Relationship;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * @template T as \Illuminate\Database\Eloquent\Model
 * @extends Valuable<T>
 */
abstract class Relation extends Valuable
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
        $this->links = $meta;
        return $this;
    }

    public function whenIncluded(): static
    {
        $this->whenIncluded = true;
        return $this;
    }

    public function whenLoaded(string $relation = null): static
    {
        return $this->when(fn(
            Request $request,
            Model $model,
            string $attribute
        ): bool => $model->relationLoaded($relation ?? $this->relation ?? $attribute));
    }

    public function whenPivotLoaded(string $table, string $accessor = null): static
    {
        return $this->when(fn(
            Request $request,
            Model $model,
            string $attribute
        ): bool => ($pivot = $model->{$accessor ?? $this->relation ?? $attribute})
            && (
                $pivot instanceof $table ||
                $pivot->getTable() === $table
            )
        );
    }

    public function resolveFor(Request $request, Model $model, string $field): Relationship
    {
        if ($this->relation instanceof Closure) {
            $value = fn() => ($this->relation)($model, $field);
        } else {
            $value = fn() => $model->getRelationValue($this->relation ?? $field);
        }

        $relation = $this->value($value);

        if ($this->whenIncluded) {
            $relation->whenIncluded();
        }

        return $relation;
    }

    abstract protected function value(Closure $value): Relationship;
}
