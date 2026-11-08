<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Resources\Concerns;

use Ark4ne\JsonApi\Compilation\Definitions;
use Ark4ne\JsonApi\Definition\RelatedResource;
use Ark4ne\JsonApi\Definition\Revision;
use Ark4ne\JsonApi\Descriptors\Describer;
use Ark4ne\JsonApi\Descriptors\MergeWhenValue;
use Ark4ne\JsonApi\Descriptors\Relations\RelationRaw;
use Ark4ne\JsonApi\Descriptors\Values\ValueMixed;
use Ark4ne\JsonApi\Exceptions\DefinitionException;
use Ark4ne\JsonApi\Resources\Relationship;
use Ark4ne\JsonApi\Support\Values;
use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\MergeValue;
use Illuminate\Http\Resources\MissingValue;
use JsonSerializable;

trait ConditionallyLoadsAttributes
{
    /**
     * @override JsonResource::when
     * Typed: the value, or a `MissingValue` (or `$default`) when the condition fails.
     *
     * @template V
     * @template D
     *
     * @param bool $condition
     * @param V|Closure():V $value
     * @param D|Closure():D $default
     *
     * @return V|D|\Illuminate\Http\Resources\MissingValue
     */
    protected function when($condition, $value, $default = new MissingValue())
    {
        return func_num_args() < 3
            ? parent::when($condition, $value)
            : parent::when($condition, $value, $default);
    }

    /**
     * Retrieve a value if a field of the resource is requested by the sparse fieldset of its type (`fields`). The
     * field is named as in the code: for a client on an older revision, by the name it has at that revision, the
     * condition failing for a field that does not exist at it.
     *
     * @template K
     *
     * @param \Illuminate\Http\Request $request
     * @param string $attribute
     * @param K $value
     *
     * @return \Illuminate\Http\Resources\MissingValue|K
     */
    protected function whenInFields(Request $request, string $attribute, mixed $value)
    {
        $context = $this->renderingContext;
        $field = $this->nameAtRevision($attribute);

        return $this->when(
            $context !== null && $field !== null && $context->fields->requests($this->toType($request), $field),
            $value,
        );
    }

    /**
     * Retrieve a value if a relationship of the resource is included (`include`). Not to be confused with
     * `whenIncluded()` of the relationships. The relationship is named as in the code: for a client on an older
     * revision, by the name it has at that revision, the condition failing for one that does not exist at it.
     *
     * @template K
     *
     * @param \Illuminate\Http\Request $request
     * @param string $relationship
     * @param K $value
     *
     * @return \Illuminate\Http\Resources\MissingValue|K
     */
    protected function whenInIncludes(Request $request, string $relationship, mixed $value)
    {
        $name = $this->includeAtRevision($relationship);

        return $this->when($name !== null && ($this->renderingContext?->includes($name) ?? false), $value);
    }

    /**
     * The name a field of the code has at the revision of the rendering, `null` when it does not exist at it: the
     * client writes `fields` and `include` in the names of its revision. Of a dotted path, the first segment is
     * translated (a struct member keeps its name). A name no shape of the resource declares (a field of `toLinks()`)
     * is kept. Unchanged for a resource declaring no revision.
     */
    private function nameAtRevision(string $field): ?string
    {
        $context = $this->renderingContext;
        // Outside a rendering (the probe of the compilation, which must not ask for its own definition): no condition
        // passes anyway.
        if ($context === null) {
            return $field;
        }

        $segments = explode('.', $field, 2);
        $first = self::fieldAtRevision(static::class, $segments[0], $context->revision);

        return $first === null || !isset($segments[1]) ? $first : "{$first}.{$segments[1]}";
    }

    /**
     * The include path of the code `$path` at the revision of the rendering: each relationship by its name at that
     * revision, following the related resource of each one; `null` when one does not exist at it. Past a polymorphic
     * or native relationship, the rest of the path is kept.
     */
    private function includeAtRevision(string $path): ?string
    {
        $context = $this->renderingContext;
        if ($context === null) {
            return $path;
        }

        $class = static::class;
        $translated = [];
        $segments = explode('.', $path);
        foreach ($segments as $index => $segment) {
            $name = self::fieldAtRevision($class, $segment, $context->revision);
            if ($name === null) {
                return null;
            }

            $translated[] = $name;
            $relationship = Definitions::for($class, $context->revision)->relationships[$name] ?? null;
            if ($relationship === null || $relationship->isNative() || count(array_unique($relationship->related)) !== 1) {
                return implode('.', [...$translated, ...array_slice($segments, $index + 1)]);
            }

            $class = RelatedResource::resolve($relationship->related[0]);
            if (!Definitions::exists($class, $context->revision)) {
                return null;
            }
        }

        return implode('.', $translated);
    }

    /**
     * The name the field of the code `$field` of `$class` has at `$revision`, `null` when it does not exist at it; a
     * name no shape declares, or a resource declaring no revision, keeps it.
     *
     * @param class-string<\Ark4ne\JsonApi\Definition\DefinitionSource> $class
     */
    private static function fieldAtRevision(string $class, string $field, ?Revision $revision): ?string
    {
        if (!Definitions::isRevised($class)) {
            return $field;
        }

        // The head too: a field `until()` a revision does not exist there.
        return Definitions::for($class, $revision)->fieldNames[$field]
            ?? (Definitions::declaresField($class, $field) ? null : $field);
    }

    /**
     * @override JsonResource::merge
     * Unconditional: the members are kept in the definition (Laravel's `merge()` goes through `mergeWhen()`).
     *
     * @param mixed $value
     *
     * @return \Illuminate\Http\Resources\MergeValue
     */
    protected function merge($value)
    {
        return new MergeValue(self::mergeable(value($value)));
    }

    /**
     * @override JsonResource::mergeWhen
     * Merged members are hidden from the definition whatever the condition, with a `merge-when` diagnostic: group
     * conditional members with `applyWhen()`, which keeps them in the definition. `mergeUnless()` relies on it.
     *
     * @param bool $condition
     * @param mixed $value
     * @param mixed $default
     *
     * @return \Illuminate\Http\Resources\MergeValue|mixed
     */
    protected function mergeWhen($condition, $value, $default = new MissingValue())
    {
        if ($condition) {
            return new MergeWhenValue(self::mergeable(value($value)));
        }

        return new MergeWhenValue(func_num_args() === 3 ? self::mergeable(value($default)) : []);
    }

    /**
     * The members to merge, as Laravel merges them: `null` merges nothing.
     *
     * @return array<array-key, mixed>
     *
     * @throws DefinitionException when the members are a scalar or an object that cannot be merged
     */
    private static function mergeable(mixed $members): array
    {
        if ($members === null) {
            return [];
        }

        if ($members instanceof Arrayable) {
            return $members->toArray();
        }

        if (is_iterable($members)) {
            return is_array($members) ? $members : iterator_to_array($members);
        }

        if ($members instanceof JsonSerializable && is_array($serialized = $members->jsonSerialize())) {
            return $serialized;
        }

        throw DefinitionException::notMergeable(get_debug_type($members));
    }

    /**
     * Groups conditional members: they are kept in the definition, marked conditional. The way to group conditions,
     * rather than `mergeWhen()`, whose members are missing from the definition.
     *
     * @param bool|Closure():boolean $condition
     * @param iterable<array-key, mixed> $data
     *
     * @return \Illuminate\Http\Resources\MergeValue
     */
    protected function applyWhen(bool|Closure $condition, iterable $data): MergeValue
    {
        return new MergeValue(collect($data)->map(function ($raw) use ($condition) {
            if ($raw instanceof Describer) {
                $value = $raw;
            } elseif ($raw instanceof Relationship) {
                $value = RelationRaw::wrap($raw->getResource(), static fn() => $raw);
            } else {
                $value = new ValueMixed($raw instanceof Closure ? $raw : static fn() => $raw);
            }

            return $value->when(static fn() => value($condition));
        }));
    }

    /**
     * @override JsonResource::whenHas
     * Support none Model resource
     *
     * Retrieve an attribute if it exists on the resource.
     *
     * @param string $attribute
     * @param mixed $value
     * @param mixed $default
     * @return \Illuminate\Http\Resources\MissingValue|mixed
     */
    public function whenHas($attribute, $value = null, $default = null)
    {
        if (func_num_args() < 3) {
            $default = new MissingValue();
        }

        if (!Values::hasAttribute($this->resource, $attribute)) {
            return value($default);
        }

        return func_num_args() === 1
            ? Values::getAttribute($this->resource, $attribute)
            : value($value, Values::getAttribute($this->resource, $attribute));
    }
}
