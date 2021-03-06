<?php

namespace Ark4ne\JsonApi\Requests\Rules;

use Ark4ne\JsonApi\Requests\Rules\Traits\UseTrans;
use Ark4ne\JsonApi\Resources\Skeleton;
use Ark4ne\JsonApi\Support\Includes as SupportIncludes;
use Illuminate\Contracts\Validation\Rule;

/**
 * @template T as \Ark4ne\JsonApi\Resources\JsonApiResource
 */
class Includes implements Rule
{
    use UseTrans;

    /**
     * @var array<int, array{":include": string, ":relation": string}>>
     */
    protected array $failures = [];

    /**
     * @param class-string<T> $resource
     */
    public function __construct(
        protected string $resource
    ) {
    }

    public function passes($attribute, $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        $desired = SupportIncludes::parse($value);
        $schema = $this->resource::schema();

        return $this->assert($schema, $desired);
    }

    /**
     * @return array<string>
     */
    public function message(): array
    {
        $base = 'validation.custom.jsonapi.includes';
        $message = $this->trans(
            "$base.invalid",
            'The selected :attribute is invalid.'
        );

        return array_merge($message, ...array_map(
            fn($failure) => $this->trans(
                "$base.invalid_includes",
                '":include" doesn\'t have relationship ":relation".',
                $failure
            ),
            $this->failures
        ));
    }

    /**
     * @param Skeleton             $schema
     * @param array<string, mixed> $desired
     * @param string               $pretend
     *
     * @return bool
     */
    private function assert(Skeleton $schema, array $desired, string $pretend = ''): bool
    {
        foreach ($desired as $relation => $sub) {
            if (!isset($schema->relationships[$relation])) {
                $this->failures[] = [
                    ':include' => $pretend ?: $schema->type,
                    ':relation' => $relation
                ];
            } else {
                $this->assert(
                    $schema->relationships[$relation],
                    $sub,
                    $pretend ? "$pretend.$relation" : $relation
                );
            }
        }

        return empty($this->failures);
    }
}
