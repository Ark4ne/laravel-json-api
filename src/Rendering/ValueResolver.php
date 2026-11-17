<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Rendering;

use Ark4ne\JsonApi\Context\JsonApiContext;
use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Resources\MergeValue;
use Illuminate\Http\Resources\MissingValue;
use Illuminate\Http\Resources\PotentiallyMissing;

/**
 * Resolves the members returned by a hook for one model (docs/architecture.md §8).
 *
 * - `ProjectedValues`, the members `ark4ne/laravel-api-revisions` rendered (attributes, meta), are given as they are.
 * - The plain members (links, the default attributes of a model) are resolved lazily: a member the sparse fieldset
 *   leaves out is never resolved, a `MergeValue` merges its members, a `MissingValue` is left out, a closure is
 *   called, any other value is kept as is.
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
        // Rendered by the base: already chosen by the sparse fieldset, decided and serialized.
        if ($members instanceof ProjectedValues) {
            return $members->values;
        }

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

            $name = $key;
            $path = $prefix === null ? (string) $name : "{$prefix}.{$name}";

            if ($type !== null && !$context->fields->allows($type, $path)) {
                continue;
            }

            $value = $member instanceof Closure ? $member() : $member;

            if (!self::isMissing($value)) {
                $resolved[$name] = self::plain($value);
            }
        }

        return $resolved;
    }

    /**
     * Collections and other arrayables become plain arrays, at any depth.
     */
    private static function plain(mixed $value): mixed
    {
        if ($value instanceof Arrayable) {
            $value = $value->toArray();
        }

        return is_array($value) ? array_map(self::plain(...), $value) : $value;
    }

    private static function isMissing(mixed $value): bool
    {
        return $value instanceof PotentiallyMissing && $value->isMissing();
    }
}
