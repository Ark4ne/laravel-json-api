<?php

namespace Ark4ne\JsonApi\Descriptors\Values;

use Ark4ne\JsonApi\Descriptors\Describer;
use Ark4ne\JsonApi\Support\Arr;
use Ark4ne\JsonApi\Support\Config;
use Ark4ne\JsonApi\Support\Fields;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr as IlluminateArr;

/**
 * @template T
 * @extends Describer<T>
 */
abstract class Value extends Describer
{
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
            Model $model,
            string $attribute
        ): bool => Fields::has($request, $attribute));
    }

    public function resolveFor(Request $request, mixed $model, string $field): mixed
    {
        if ($this->attribute instanceof Closure) {
            $value = ($this->attribute)($model, $field);
        } elseif ($model instanceof Model) {
            $value = $model->getAttribute($this->attribute ?? $field);
        } elseif (IlluminateArr::accessible($model)) {
            $value = $model[$this->attribute ?? $field] ?? null;
        } else {
            $value = $model->{$this->attribute ?? $field} ?? null;
        }

        return $value === null && $this->nullable
            ? null
            : $this->value($value);
    }

    abstract protected function value(mixed $of): mixed;
}
