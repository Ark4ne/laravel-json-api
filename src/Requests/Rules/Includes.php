<?php

namespace Ark4ne\JsonApi\Requests\Rules;

use Ark4ne\JsonApi\Requests\Rules\Traits\UseTrans;
use Ark4ne\JsonApi\Resources\Skeleton;
use Ark4ne\JsonApi\Support\Includes as SupportIncludes;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * @template T as \Ark4ne\JsonApi\Resources\JsonApiResource
 */
class Includes implements ValidationRule
{
    use UseTrans;

    private const BASE = 'validation.custom.jsonapi.fields';

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

    public function validate(string $attribute, mixed $value, \Closure $fail): void
    {
        if (!is_string($value)) {
            $fail($this->trans(
                sprintf('%s.invalid', self::BASE),
                'The selected :attribute is invalid.'
            ));
            return;
        }

        $desired = SupportIncludes::parse($value);
        $schema = $this->resource::schema();

        if (!$this->assert($schema, $desired)) {
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
            'The selected :attribute is invalid.'
        );

        return array_merge([$message], array_map(
            fn($failure) => $this->trans(
                sprintf('%s.invalid_includes', self::BASE),
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
