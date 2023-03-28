<?php

namespace Ark4ne\JsonApi\Descriptors\Values;

use Ark4ne\JsonApi\Descriptors\Describer;
use Ark4ne\JsonApi\Support\Config;
use Ark4ne\JsonApi\Support\Fields;
use Ark4ne\JsonApi\Support\Values;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * @template T
 * @extends Describer<T>
 */
abstract class Value extends Describer
{
    protected ?bool $autoWhenHas = null;

    protected bool $nullable;

    public function __construct(
        protected null|string|Closure $attribute
    ) {
        $this->nullable = Config::$nullable;
    }

    public function retriever(): string|Closure|null
    {
        return $this->attribute;
    }

    public function nullable(bool $nullable = true): static
    {
        $this->nullable = $nullable;
        return $this;
    }

    public function isNullable(): bool
    {
        return $this->nullable;
    }

    /**
     * Add or reset auto check when has attribute.
     *
     * @param bool $autoWhenHas
     * @return static
     */
    public function autoWhenHas(bool $autoWhenHas = true): static
    {
        $this->autoWhenHas = $autoWhenHas;

        return $this;
    }

    /**
     * Display attribute whether the accessor attribute has been appended.
     *
     * @return static
     */
    public function whenAppended(): static
    {
        return $this->when(static fn(
            Request $request,
            Model $model,
            string $attribute
        ): bool => $model->hasAppended($attribute));
    }

    /**
     * Display attribute only when it was specified in fields
     *
     * @return static
     */
    public function whenInFields(): static
    {
        return $this->when(static fn(
            Request $request,
            mixed $model,
            string $attribute
        ): bool => Fields::has($request, $attribute));
    }

    /**
     * Display an attribute if it exists on the resource.
     *
     * @param string|null $field
     * @return static
     */
    public function whenHas(?string $field = null): static
    {
        return $this->when(static fn(
            Request $request,
            mixed $model,
            string $attribute
        ): bool => Values::hasAttribute($model, $field ?? $attribute));
    }

    public function resolveFor(Request $request, mixed $model, string $field): mixed
    {
        if ($this->attribute instanceof Closure) {
            $value = ($this->attribute)($model, $field);
        } else {
            $value = Values::getAttribute($model, $this->attribute ?? $field);
        }

        return $value === null && $this->nullable
            ? null
            : $this->value($value);
    }

    protected function check(Request $request, mixed $model, string $attribute): bool
    {
        if (($this->autoWhenHas ?? false) && !($this->attribute instanceof Closure)) {
            $this->whenHas($this->attribute);
        }

        return parent::check($request, $model, $attribute);
    }

    abstract protected function value(mixed $of): mixed;
}
