<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Rendering;

use Ark4ne\JsonApi\Context\JsonApiContext;
use Ark4ne\JsonApi\Descriptors\Describer;
use Ark4ne\JsonApi\Descriptors\Values\Value;
use Ark4ne\JsonApi\Descriptors\Values\ValueStruct;
use Ark4ne\JsonApi\Support\Arr;
use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Resources\MergeValue;
use Illuminate\Http\Resources\MissingValue;
use Illuminate\Http\Resources\PotentiallyMissing;

/**
 * Resolves the members returned by a hook for one model, lazily (docs/architecture.md §8): sparse fieldset filter,
 * then conditions, then value. A member that is not requested is never resolved, struct members included.
 *
 * - `applyWhen()` (`MergeValue`) merges its members; a `MissingValue` (`when()`, `mergeWhen()`) is left out.
 * - A descriptor gives its value when its conditions pass; `whenInFields()` only when it is explicitly requested.
 * - A struct is resolved member by member, each filtered by its dotted path.
 * - A closure is called; any other value is kept as is.
 */
final readonly class ValueResolver
{
    /**
     * @param iterable<array-key, mixed> $members
     * @param string|null $type The type whose sparse fieldset filters the members; `null` for none (links, meta)
     *
     * @return array<array-key, mixed>
     */
    public function resolve(iterable $members, mixed $model, JsonApiContext $context, ?string $type = null): array
    {
        return $this->members($members, $model, $context, $type, null);
    }

    /**
     * @param iterable<array-key, mixed> $members
     *
     * @return array<array-key, mixed>
     */
    private function members(
        iterable $members,
        mixed $model,
        JsonApiContext $context,
        ?string $type,
        ?string $prefix,
    ): array {
        $resolved = [];

        foreach ($members as $key => $member) {
            if (is_int($key) && $member instanceof MergeValue) {
                /** @var iterable<array-key, mixed> $merged */
                $merged = $member->data;
                $resolved = array_replace($resolved, $this->members($merged, $model, $context, $type, $prefix));

                continue;
            }

            if (self::isMissing($member)) {
                continue;
            }

            $name = Describer::retrieveName($member, $key);
            $path = $prefix === null ? (string) $name : "{$prefix}.{$name}";

            if ($type !== null && !self::requested($member, $type, $path, $context)) {
                continue;
            }

            $value = $this->value($member, (string) $name, $path, $model, $context, $type);

            if (!self::isMissing($value)) {
                $resolved[$name] = self::plain($value);
            }
        }

        return $resolved;
    }

    private function value(
        mixed $member,
        string $name,
        string $path,
        mixed $model,
        JsonApiContext $context,
        ?string $type,
    ): mixed {
        if ($member instanceof ValueStruct) {
            return $member->passes($context->request, $model, $name)
                ? $this->members($member->members($model, $name), $model, $context, $type, $path)
                : new MissingValue();
        }

        if ($member instanceof Describer) {
            return $member->valueFor($context->request, $model, $name, $context->configuration);
        }

        return $member instanceof Closure ? $member() : $member;
    }

    /**
     * A member is rendered when the sparse fieldset allows it; a member only in fields when it requests it explicitly.
     */
    private static function requested(mixed $member, string $type, string $path, JsonApiContext $context): bool
    {
        return $member instanceof Value && $member->isSparse()
            ? $context->fields->requests($type, $path)
            : $context->fields->allows($type, $path);
    }

    /**
     * Collections and other arrayables become plain arrays, at any depth.
     */
    private static function plain(mixed $value): mixed
    {
        if ($value instanceof Arrayable) {
            $value = $value->toArray();
        }

        return is_array($value) ? Arr::toArray($value) : $value;
    }

    private static function isMissing(mixed $value): bool
    {
        return $value instanceof PotentiallyMissing && $value->isMissing();
    }
}
