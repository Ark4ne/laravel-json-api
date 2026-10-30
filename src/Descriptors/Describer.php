<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Descriptors;

use Ark4ne\JsonApi\Configuration\Configuration;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\MissingValue;
use Illuminate\Support\Arr;

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
        return $this->withRule(static fn(
            Request $request,
            mixed   $model,
            string  $attribute,
        ): bool => $condition instanceof Closure ? $condition($request, $model, $attribute) : $condition);
    }

    /**
     * Display field whether the given condition is true.
     *
     * @param bool|Closure(Request, T, string):bool $condition
     *
     * @return static
     */
    public function unless(bool|Closure $condition): static
    {
        return $this->withRule(static fn(
            Request $request,
            mixed   $model,
            string  $attribute,
        ): bool => !($condition instanceof Closure ? $condition($request, $model, $attribute) : $condition));
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
            string  $attribute,
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
            string  $attribute,
        ): bool => filled($this->retrieveValue($model, $attribute)));
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param T $model
     * @param string $attribute
     * @param Configuration|null $configuration The defaults the descriptor leaves unset; those of a fresh installation
     *                                          if `null`
     *
     * @return mixed
     */
    public function valueFor(
        Request $request,
        mixed $model,
        string $attribute,
        ?Configuration $configuration = null,
    ): mixed {
        if (!$this->check($request, $model, $attribute)) {
            return new MissingValue();
        }

        return $this->resolveFor($request, $model, $attribute, $configuration);
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

    /**
     * Whether the conditions of the descriptor pass for the model, without resolving its value.
     *
     * @internal Read by the renderer.
     */
    public function passes(Request $request, mixed $model, string $attribute): bool
    {
        return $this->check($request, $model, $attribute);
    }

    /**
     * Whether a condition applies: the field may be left out of the rendering.
     */
    public function isConditional(): bool
    {
        return $this->rules !== [];
    }

    /**
     * A copy of this descriptor with one more property set: descriptors are immutable (docs/architecture.md §5).
     */
    protected function with(string $property, mixed $value): static
    {
        $clone = clone $this;
        // The single place where a descriptor is modified, always on a fresh clone.
        $clone->{$property} = $value; // @phpstan-ignore property.dynamicName

        return $clone;
    }

    /**
     * @param Closure(Request, T, string): bool $rule
     */
    private function withRule(Closure $rule): static
    {
        return $this->with('rules', [...$this->rules, $rule]);
    }

    private function retrieveValue(mixed $model, string $attribute): mixed
    {
        $value = static fn($attr) => Arr::accessible($model) ? $model[$attr] : $model->$attr;

        $retriever = $this->retriever();
        if ($retriever === null) {
            return $value($attribute);
        }
        if (is_string($retriever)) {
            return $value($retriever);
        }
        return $retriever();
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param T $model
     * @param string $attribute
     * @param Configuration|null $configuration
     *
     * @return mixed
     */
    abstract protected function resolveFor(
        Request $request,
        mixed $model,
        string $attribute,
        ?Configuration $configuration = null,
    ): mixed;

    /**
     * @return string|Closure|null
     */
    abstract public function retriever(): string|Closure|null;

    /**
     * @param mixed $value
     * @param int|string $key
     *
     * @return int|string
     */
    public static function retrieveName(mixed $value, int|string $key, ?string $prefix = null): int|string
    {
        if (is_int($key) && $value instanceof self && is_string($retriever = $value->retriever())) {
            return $prefix ? $prefix . '.' . $retriever : $retriever;
        }

        return $prefix ? $prefix . '.' . $key : $key;
    }
}
