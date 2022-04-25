<?php

namespace Ark4ne\JsonApi\Requests\Rules;

use Ark4ne\JsonApi\Requests\Rules\Traits\UseTrans;
use Ark4ne\JsonApi\Support\Fields as SupportFields;
use Illuminate\Contracts\Validation\Rule;

/**
 * @template T as \Ark4ne\JsonApi\Resources\JsonApiResource
 */
class Fields implements Rule
{
    use UseTrans;

    protected array $failures;

    /**
     * @param class-string<T> $resource
     */
    public function __construct(
        protected string $resource
    ) {
    }

    public function passes($attribute, $value): bool
    {
        $desired = SupportFields::parse($value);
        $schema = $this->resource::schema();

        return $this->assert($schema, $desired);
    }

    public function message()
    {
        $base = 'validation.custom.jsonapi.fields';
        $message = $this->trans(
            "$base.invalid",
            'The selected :attribute is invalid.'
        );

        return array_merge($message, ...array_map(
            fn($failure) => isset($failure[':fields'])
                ? $this->trans(
                    "$base.invalid_fields",
                    '":resource" doesn\'t have fields ":fields".',
                    $failure
                )
                : $this->trans(
                    "$base.invalid_resource",
                    '":resource" doesn\'t exists.',
                    $failure
                ),
            $this->failures
        ));
    }

    private function assert(object $schema, array $desired): bool
    {
        $resources = $this->extractSchemaFields($schema);

        foreach ($desired as $resource => $fields) {
            if (!isset($resources[$resource])) {
                $this->failures[] = [
                    ':resource' => $resource
                ];
            } elseif (!empty($diff = array_diff($fields, $resources[$resource]))) {
                $this->failures[] = [
                    ':resource' => $resource,
                    ':fields' => implode(',', $diff)
                ];
            }
        }

        return empty($this->failures);
    }

    private function extractSchemaFields(object $schema, array $resources = []): array
    {
        if (isset($resources[$schema->type])) {
            return $resources;
        }

        $resources[$schema->type] = $schema->fields;

        foreach ($schema->relationships as $relationship) {
            $resources = $this->extractSchemaFields($relationship, $resources);
        }

        return $resources;
    }
}
