<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Requests\Rules;

use Ark4ne\JsonApi\Compilation\Definitions;
use Ark4ne\JsonApi\Context\SparseFieldsets;
use Ark4ne\JsonApi\Definition\RelatedResource;
use Ark4ne\JsonApi\Requests\Rules\Traits\UseTrans;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates a sparse fieldset against the definitions of the resource and of every resource it relates to: the fields
 * of a type are its attributes, its struct members by dotted path, and its relationships.
 *
 * @template T as \Ark4ne\JsonApi\Resources\JsonApiResource|\Ark4ne\JsonApi\Resources\JsonApiCollection
 */
class Fields implements ValidationRule
{
    use UseTrans;

    private const BASE = 'validation.custom.jsonapi.fields';

    /**
     * @var array<int, array{":resource": string, ":fields"?: string}>
     */
    protected array $failures = [];

    /**
     * @param class-string<T> $resource
     */
    public function __construct(
        protected string $resource,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_array($value)) {
            $fail($this->trans(
                sprintf('%s.invalid', self::BASE),
                'The selected :attribute is invalid.',
            ));
            return;
        }

        foreach ($value as $fields) {
            if ($fields !== null && !is_string($fields)) {
                $fail($this->trans(sprintf('%s.invalid', self::BASE), 'The selected :attribute is invalid.'));

                return;
            }
        }

        // Parsed as the rendering parses it.
        $desired = SparseFieldsets::parse($value)->all();

        if (!$this->assert($this->fieldsByType($this->resource), $desired)) {

            foreach ($this->message() as $message) {
                $fail($message);
            }
        }
    }

    /**
     * @return array<string>
     */
    private function message(): array
    {
        $message = $this->trans(
            sprintf('%s.invalid', self::BASE),
            'The selected :attribute is invalid.',
        );

        return array_merge([$message], array_map(
            fn($failure) =>  isset($failure[':fields'])
                ? $this->trans(
                    sprintf('%s.invalid_fields', self::BASE),
                    '":resource" doesn\'t have fields ":fields".',
                    $failure,
                )
                : $this->trans(
                    sprintf('%s.invalid_resource', self::BASE),
                    '":resource" doesn\'t exists.',
                    $failure,
                ),
            $this->failures,
        ));
    }

    /**
     * @param array<string, string[]> $resources
     * @param array<string, string[]> $desired
     *
     * @return bool
     */
    private function assert(array $resources, array $desired): bool
    {
        foreach ($desired as $resource => $fields) {
            if (!isset($resources[$resource])) {
                $this->failures[] = [
                    ':resource' => $resource,
                ];
            } elseif (!empty($diff = array_diff($fields, $resources[$resource]))) {
                $this->failures[] = [
                    ':resource' => $resource,
                    ':fields' => implode(',', $diff),
                ];
            }
        }

        return empty($this->failures);
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
            $resources = $this->fieldsByType($relationship->related, $resources);
        }

        return $resources;
    }
}
