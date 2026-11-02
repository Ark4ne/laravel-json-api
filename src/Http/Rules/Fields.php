<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Http\Rules;

use Ark4ne\JsonApi\Compilation\Definitions;
use Ark4ne\JsonApi\Context\SparseFieldsets;
use Ark4ne\JsonApi\Definition\RelatedResource;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\Resources\JsonApi\JsonApiRequest;
use Throwable;

/**
 * Validates a sparse fieldset against the definitions of the resource and of every resource it relates to: the fields
 * of a type are its attributes, its struct members by dotted path, and its relationships. The fields of a native
 * resource (`RelationshipDefinition::$native`) are unknown: those of its type are accepted as they are. Its type is read
 * without model (`toType()`, otherwise the default of Laravel); when it cannot be, a type no definition has is
 * accepted as is.
 *
 * Its messages are the translations `jsonapi::validation.fields.*` (`vendor:publish --tag=jsonapi-lang`).
 *
 * @template T as \Ark4ne\JsonApi\Resources\JsonApiResource|\Ark4ne\JsonApi\Resources\JsonApiCollection
 */
final class Fields implements ValidationRule
{
    /**
     * @param class-string<T> $resource
     */
    public function __construct(
        private readonly string $resource,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_array($value) || array_filter($value, static fn($fields) => $fields !== null && !is_string($fields)) !== []) {
            $fail('jsonapi::validation.fields.invalid')->translate();

            return;
        }

        // Parsed as the rendering parses it.
        $untyped = false;
        $failures = $this->failures(
            $this->fieldsByType($this->resource, untyped: $untyped),
            SparseFieldsets::parse($value)->all(),
            $untyped,
        );

        if ($failures === []) {
            return;
        }

        $fail('jsonapi::validation.fields.invalid')->translate();
        foreach ($failures as $failure) {
            $key = isset($failure['fields']) ? 'invalid_fields' : 'invalid_resource';
            $fail("jsonapi::validation.fields.{$key}")->translate($failure);
        }
    }

    /**
     * @param array<string, string[]|null> $resources The fields by type, `null` for those of a native resource
     * @param array<string, string[]> $desired
     * @param bool $untyped Whether a native resource of unknown type is reachable: a type no definition has is then
     *                      accepted
     *
     * @return list<array{resource: string, fields?: string}>
     */
    private function failures(array $resources, array $desired, bool $untyped): array
    {
        $failures = [];

        foreach ($desired as $resource => $fields) {
            if (!array_key_exists($resource, $resources)) {
                if (!$untyped) {
                    $failures[] = ['resource' => $resource];
                }
            } elseif ($resources[$resource] !== null && ($diff = array_diff($fields, $resources[$resource])) !== []) {
                $failures[] = ['resource' => $resource, 'fields' => implode(',', $diff)];
            }
        }

        return $failures;
    }

    /**
     * The fields of every type reachable from the resource, the first definition met winning for a type.
     *
     * @param array<string, string[]|null> $resources
     * @param bool $untyped Set when a native resource of unknown type is met
     *
     * @return array<string, string[]|null> The fields by type, `null` for those of a native resource
     */
    private function fieldsByType(string $class, array $resources = [], bool &$untyped = false): array
    {
        $definition = Definitions::for(RelatedResource::resolve($class));

        if (array_key_exists($definition->type, $resources)) {
            return $resources;
        }

        $resources[$definition->type] = $definition->fields();

        foreach ($definition->relationships as $relationship) {
            if ($relationship->native !== null) {
                $type = self::nativeType($relationship->native);
                $untyped = $untyped || $type === null;

                if ($type !== null && !array_key_exists($type, $resources)) {
                    $resources[$type] = null;
                }
            }

            foreach ($relationship->related as $related) {
                $resources = $this->fieldsByType($related, $resources, $untyped);
            }
        }

        return $resources;
    }

    /**
     * The type of a native resource, read without model; `null` when its `toType()` needs one.
     *
     * @param class-string<\Illuminate\Http\Resources\JsonApi\JsonApiResource> $class
     */
    private static function nativeType(string $class): ?string
    {
        try {
            return (new $class(null))->resolveResourceType(new JsonApiRequest());
        } catch (Throwable) {
            return null;
        }
    }
}
