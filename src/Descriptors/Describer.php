<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Descriptors;

use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Definition\Revision;
use Ark4ne\JsonApi\Support\Values;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\MissingValue;

/**
 * @template T
 */
abstract class Describer
{
    /**
     * @var array<Closure(Request, T, string, Describer<T>): bool> Each receives the descriptor it runs on: a copy of the
     *      one it was declared on may read another source (`readingFrom()`)
     */
    protected array $rules = [];

    /**
     * @var list<FieldRevision<T>> What the field was up to some revisions (`was()`), in the order of the calls
     */
    protected array $revisions = [];

    /**
     * The name of the head field this descriptor stands for at a revision (`standingFor()`), `null` for the head itself:
     * the `$attribute` its conditions, its source and its closures receive, whatever name it is rendered under (model
     * side, always the head).
     */
    protected ?string $headName = null;

    /** The oldest revision the field exists at (`since()`), `null` for every revision. */
    protected ?Revision $since = null;

    /** The newest revision the field exists at (`until()`), `null` for the head. */
    protected ?Revision $until = null;

    /**
     * @var list<'since'|'until'> The bounds declared more than once, reported by the compilation
     */
    protected array $redeclaredBounds = [];

    /**
     * The field exists from `$revision` included; left out for the older revisions. Optional for an addition, required
     * when the field takes the name of an older field of the other kind.
     */
    public function since(Revision $revision): static
    {
        return $this->withBound('since', $revision);
    }

    /**
     * The field exists up to `$revision` included; left out for the newer revisions and for the head.
     */
    public function until(Revision $revision): static
    {
        return $this->withBound('until', $revision);
    }

    /**
     * @internal Read by the selection of the shape of a field for a revision.
     */
    public function sinceRevision(): ?Revision
    {
        return $this->since;
    }

    /**
     * @internal Read by the selection of the shape of a field for a revision.
     */
    public function untilRevision(): ?Revision
    {
        return $this->until;
    }

    /**
     * The bounds (`since()`, `until()`) declared more than once on the field: the last call wins.
     *
     * @return list<'since'|'until'>
     *
     * @internal Read by the compilation, which reports them.
     */
    public function redeclaredBounds(): array
    {
        return $this->redeclaredBounds;
    }

    /**
     * What the field was up to each revision (`was()`), in the order of the calls: the order of the revisions decides
     * which one applies.
     *
     * @return list<FieldRevision<T>>
     *
     * @internal Read by the selection of the shape of a field for a revision.
     */
    public function revisions(): array
    {
        return $this->revisions;
    }

    /**
     * @param 'since'|'until' $bound
     */
    private function withBound(string $bound, Revision $revision): static
    {
        $previous = $bound === 'since' ? $this->since : $this->until;
        $clone = $this->with($bound, $revision);

        return $previous === null || in_array($bound, $this->redeclaredBounds, true)
            ? $clone
            : $clone->with('redeclaredBounds', [...$this->redeclaredBounds, $bound]);
    }

    /**
     * This descriptor standing for the head field `$headName` at a revision: run with the head name as `$attribute`
     * whatever name it is rendered under, reading the head source when it names none of its own. A head read by a
     * closure gives no source.
     *
     * @internal Used by the selection of the shape of a field for a revision.
     */
    public function standingFor(string $headName, string|Closure $headSource): static
    {
        $descriptor = is_string($headSource) ? $this->readingFrom($headSource) : $this;

        return $descriptor->with('headName', $headName);
    }

    /**
     * A copy of this descriptor with one more `was()`.
     *
     * @param Describer<T>|null $value
     */
    protected function withRevision(Revision $revision, ?string $name, ?Describer $value): static
    {
        return $this->with('revisions', [...$this->revisions, new FieldRevision($revision, $name, $value)]);
    }

    /**
     * Renders the member only when the condition holds: a closure receives the request, the model and the name of the
     * member, and may return any truthy value, as with Laravel's `when()`.
     *
     * @param bool|Closure(Request, T, string): mixed $condition
     *
     * @return static
     */
    public function when(bool|Closure $condition): static
    {
        return $this->withRule(static fn(
            Request $request,
            mixed   $model,
            string  $attribute,
        ): bool => (bool) ($condition instanceof Closure ? $condition($request, $model, $attribute) : $condition));
    }

    /**
     * Renders the member only when the condition does not hold (see `when()`).
     *
     * @param bool|Closure(Request, T, string): mixed $condition
     *
     * @return static
     */
    public function unless(bool|Closure $condition): static
    {
        return $this->withRule(static fn(
            Request $request,
            mixed   $model,
            string  $attribute,
        ): bool => !(bool) ($condition instanceof Closure ? $condition($request, $model, $attribute) : $condition));
    }

    /**
     * Display field whether the accessor field is not null.
     *
     * @return static
     */
    public function whenNotNull(): static
    {
        return $this->withRule(static fn(
            Request   $request,
            mixed     $model,
            string    $attribute,
            Describer $describer,
        ): bool => null !== $describer->retrieveValue($model, $attribute));
    }

    /**
     * Display field whether the accessor field is filled.
     *
     * @return static
     */
    public function whenFilled(): static
    {
        return $this->withRule(static fn(
            Request   $request,
            mixed     $model,
            string    $attribute,
            Describer $describer,
        ): bool => filled($describer->retrieveValue($model, $attribute)));
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
        $attribute = $this->headName ?? $attribute;

        foreach ($this->rules as $rule) {
            if (!$rule($request, $model, $attribute, $this)) {
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
     * A copy of this descriptor with one more condition, receiving the descriptor it runs on: a condition reading the
     * source reads it there, never on `$this`, which a copy may have replaced (`readingFrom()`).
     *
     * @param Closure(Request, T, string, Describer<T>): bool $rule
     */
    protected function withRule(Closure $rule): static
    {
        return $this->with('rules', [...$this->rules, $rule]);
    }

    private function retrieveValue(mixed $model, string $attribute): mixed
    {
        $value = static fn(string $attr): mixed => Values::getAttribute($model, $attr);

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
     * This descriptor reading `$source` when it names no source of its own (`standingFor()`).
     */
    abstract protected function readingFrom(string $source): static;

    /**
     * @param mixed $value
     * @param int|string $key
     *
     * @return int|string
     */
    public static function retrieveName(mixed $value, int|string $key, ?string $prefix = null): int|string
    {
        if (is_int($key) && $value instanceof self && is_string($retriever = $value->retriever())) {
            $key = $retriever;
        }

        return $prefix !== null && $prefix !== '' ? $prefix . '.' . $key : $key;
    }
}
