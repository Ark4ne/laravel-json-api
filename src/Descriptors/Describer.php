<?php

namespace Ark4ne\JsonApi\Descriptors;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\MissingValue;

/**
 * @template T
 */
abstract class Describer
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
            mixed   $model,
            string  $attribute
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
            mixed   $model,
            string  $attribute
        ): bool => null !== $this->retrieveValue($model, $attribute));
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
            mixed   $model,
            string  $attribute
        ): bool => filled($this->retrieveValue($model, $attribute)));
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param T $model
     * @param string $field
     *
     * @return mixed
     */
    public function valueFor(Request $request, mixed $model, string $field): mixed
    {
        if (!$this->check($request, $model, $field)) {
            return new MissingValue();
        }

        return $this->resolveFor($request, $model, $field);
    }

    /**
     * Checks if the field should be displayed
     *
     * @param \Illuminate\Http\Request $request
     * @param T $model
     * @param string $attribute
     *
     * @return bool
     */
    protected function check(Request $request, mixed $model, string $attribute): bool
    {
        foreach ($this->rules as $rule) {
            if (!$rule($request, $model, $attribute)) {
                return false;
            }
        }

        return true;
    }

    private function retrieveValue(mixed $model, string $attribute): mixed
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
     * @param T $model
     * @param string $field
     *
     * @return mixed
     */
    abstract protected function resolveFor(Request $request, mixed $model, string $field): mixed;

    /**
     * @return string|Closure|null
     */
    abstract public function retriever(): null|string|Closure;

    /**
     * @param mixed $value
     * @param int|string $key
     *
     * @return int|string
     */
    public static function retrieveName(mixed $value, int|string $key): int|string
    {
        if (is_int($key) && $value instanceof self && is_string($retriever = $value->retriever())) {
            return $retriever;
        }

        return $key;
    }
}
