<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Http\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;

/**
 * Validates that a relationship object sent holds its resource linkage (`data`), which may be `null` (a to-one
 * relationship cleared) or an empty list (a to-many one): Laravel's `required` refuses both, its `present` a
 * relationship that is not sent. Declared on the linkage by `Http\Body::toOne()` and `Http\Body::toMany()`.
 *
 * Its message is the translation `jsonapi::validation.linkage.missing` (`vendor:publish --tag=jsonapi-lang`).
 *
 * @see https://jsonapi.org/format/#document-resource-object-linkage
 */
final class Linkage implements ValidationRule, DataAwareRule
{
    /** Runs on a linkage that is not sent. */
    public bool $implicit = true;

    /** @var array<array-key, mixed> */
    private array $data = [];

    /**
     * @param array<array-key, mixed> $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $relationship = Arr::get($this->data, implode('.', array_slice(explode('.', $attribute), 0, -1)));

        if (is_array($relationship) && !Arr::has($this->data, $attribute)) {
            $fail('jsonapi::validation.linkage.missing')->translate();
        }
    }
}
