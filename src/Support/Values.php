<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Support;

use ArrayAccess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MergeValue;
use Illuminate\Http\Resources\PotentiallyMissing;
use Illuminate\Support\Arr;

final class Values
{
    /**
     * The members of a hook, the `MergeValue`s merged in place, at any depth.
     *
     * @param iterable<mixed> $data
     *
     * @return array<array-key, mixed>
     */
    public static function mergeValues(iterable $data): array
    {
        $data = is_array($data) ? $data : iterator_to_array($data);

        $index = -1;

        foreach ($data as $key => $value) {
            $index++;

            if (is_iterable($value)) {
                $data[$key] = self::mergeValues($value);

                continue;
            }

            if (is_numeric($key) && $value instanceof MergeValue) {
                $first = array_slice($data, 0, $index, true);
                $second = array_slice($data, $index + 1, null, true);

                $first = self::mergeIgnoreMissing($first, self::mergeValues($value->data));

                if (Arr::isList($value->data)) {
                    return array_merge($first, self::mergeValues(array_values($second)));
                }

                return self::mergeIgnoreMissing($first, self::mergeValues($second));
            }
        }

        return $data;
    }

    /**
     * Merge two array without erase concrete value by missing value.
     *
     * @param array<array-key, mixed> $array
     * @param array<array-key, mixed> $merge
     *
     * @return array<array-key, mixed>
     */
    private static function mergeIgnoreMissing(array $array, array $merge): array
    {
        foreach ($merge as $key => $item) {
            if (!isset($array[$key]) || self::isMissing($array[$key]) || !self::isMissing($item)) {
                $array[$key] = $item;
            }
        }

        return $array;
    }

    /**
     * @param mixed|PotentiallyMissing|JsonResource $resource
     *
     * @return bool
     */
    public static function isMissing(mixed $resource): bool
    {
        return ($resource instanceof PotentiallyMissing && $resource->isMissing())
            || ($resource instanceof JsonResource
                && $resource->resource instanceof PotentiallyMissing
                && $resource->resource->isMissing());
    }

    /**
     * Whether a resource (a model, an array, an `ArrayAccess`, an object, or one of them wrapped by a resource) has the
     * attribute.
     */
    public static function hasAttribute(mixed $object, int|string $attribute): bool
    {
        return match (true) {
            $object instanceof JsonResource => self::hasAttribute($object->resource, $attribute),
            $object instanceof Model => array_key_exists($attribute, $object->getAttributes()),
            is_array($object) || $object instanceof ArrayAccess => Arr::exists($object, $attribute),
            is_object($object) => property_exists($object, (string) $attribute),
            default => false,
        };
    }

    /**
     * The attribute of a resource (a model, an array, an `ArrayAccess`, an object, or one of them wrapped by a resource),
     * `null` when missing.
     */
    public static function getAttribute(mixed $object, int|string $attribute): mixed
    {
        return match (true) {
            $object instanceof JsonResource => self::getAttribute($object->resource, $attribute),
            $object instanceof Model => $object->getAttribute((string) $attribute),
            is_array($object) || $object instanceof ArrayAccess => $object[$attribute] ?? null,
            // Any attribute of any object, magic ones included: the name is data.
            is_object($object) => $object->{(string) $attribute} ?? null, // @phpstan-ignore property.dynamicName
            default => null,
        };
    }
}
