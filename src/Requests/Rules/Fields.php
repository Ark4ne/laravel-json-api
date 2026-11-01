<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Requests\Rules;

use Ark4ne\JsonApi\Compilation\Definitions;
use Ark4ne\JsonApi\Context\SparseFieldsets;
use Ark4ne\JsonApi\Definition\RelatedResource;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates a sparse fieldset against the definitions of the resource and of every resource it relates to: the fields
 * of a type are its attributes, its struct members by dotted path, and its relationships.
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
        $failures = $this->failures($this->fieldsByType($this->resource), SparseFieldsets::parse($value)->all());

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
     * @param array<string, string[]> $resources
     * @param array<string, string[]> $desired
     *
     * @return list<array{resource: string, fields?: string}>
     */
    private function failures(array $resources, array $desired): array
    {
        $failures = [];

        foreach ($desired as $resource => $fields) {
            if (!isset($resources[$resource])) {
                $failures[] = ['resource' => $resource];
            } elseif (($diff = array_diff($fields, $resources[$resource])) !== []) {
                $failures[] = ['resource' => $resource, 'fields' => implode(',', $diff)];
            }
        }

        return $failures;
    }

    /**
     * The fields of every type reachable from the resource, the first definition met winning for a type.
     *
     * @param array<string, string[]> $resources
     *
     * @return array<string, string[]>
     */
    private function fieldsByType(string $class, array $resources = []): array
    {
        $definition = Definitions::for(RelatedResource::resolve($class));

        if (isset($resources[$definition->type])) {
            return $resources;
        }

        $resources[$definition->type] = $definition->fields();

        foreach ($definition->relationships as $relationship) {
            foreach ($relationship->related as $related) {
                $resources = $this->fieldsByType($related, $resources);
            }
        }

        return $resources;
    }
}
