<?php

namespace Ark4ne\JsonApi\Descriptors;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\MissingValue;

/**
 * @template T as \Illuminate\Database\Eloquent\Model
 */
abstract class Valuable
{
    /**
     * @var array<Closure(Request, T, string): bool>
     */
    protected array $rules = [];

    /**
     * Display field whether the given condition is true.
     *
     * @param bool|Closure(Request, T, string):bool $condition
     *
     * @return static
     */
    public function when(bool|Closure $condition): static
    {
        $this->rules[] = static fn(
            Request $request,
            Model $model,
            string $attribute
        ): bool => value($condition, $request, $model, $attribute);

        return $this;
    }

    /**
     * Display field whether the accessor field is not null.
     *
     * @return static
     */
    public function whenNotNull(): static
    {
        return $this->when(fn(
            Request $request,
            Model $model,
            string $attribute
        ): bool => null !== $this->valueForModel($model, $attribute));
    }

    /**
     * Display field whether the accessor field is filled.
     *
     * @return static
     */
    public function whenFilled(): static
    {
        return $this->when(fn(
            Request $request,
            Model $model,
            string $attribute
        ): bool => filled($this->valueForModel($model, $attribute)));
    }

    /**
     * Checks if the field should be displayed
     *
     * @param \Illuminate\Http\Request            $request
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param string                              $attribute
     *
     * @return bool
     */
    public function check(Request $request, Model $model, string $attribute): bool
    {
        foreach ($this->rules as $rule) {
            if (!$rule($request, $model, $attribute)) {
                return false;
            }
        }

        return true;
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
        if (!$this->check($request, $model, $field)) {
            return new MissingValue();
        }

        return $this->resolveFor($request, $model, $field);
    }

    private function valueForModel(Model $model, string $attribute): mixed
    {
        $retriever = $this->retriever();
        if ($retriever === null) {
            return $model->$attribute;
        }
        if (is_string($retriever)) {
            return $model->$retriever;
        }
        return $retriever();
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param T                        $model
     * @param string                   $field
     *
     * @return mixed
     */
    abstract protected function resolveFor(Request $request, Model $model, string $field): mixed;

    /**
     * @return string|Closure|null
     */
    abstract public function retriever(): null|string|Closure;
}
