<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Requests\Rules;

use Ark4ne\JsonApi\Compilation\Definitions;
use Ark4ne\JsonApi\Definition\RelatedResource;
use Ark4ne\JsonApi\Requests\Rules\Traits\UseTrans;
use Ark4ne\JsonApi\Support\Includes as SupportIncludes;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates an include path against the relationships declared by the definitions of the resources it goes through.
 *
 * @template T as \Ark4ne\JsonApi\Resources\JsonApiResource|\Ark4ne\JsonApi\Resources\JsonApiCollection
 */
class Includes implements ValidationRule
{
    use UseTrans;

    private const BASE = 'validation.custom.jsonapi.fields';

    /**
     * @var array<int, array{":include": string, ":relation": string}>
     */
    protected array $failures = [];

    /**
     * @var list<string> The include paths deeper than the maximum depth
     */
    protected array $tooDeep = [];

    /**
     * @param class-string<T> $resource
     * @param positive-int|null $maxDepth Deepest include path accepted; the configured one (`include.max-depth`) when
     *                                    `null`
     */
    public function __construct(
        protected string $resource,
        protected ?int $maxDepth = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value)) {
            $fail($this->trans(
                sprintf('%s.invalid', self::BASE),
                'The selected :attribute is invalid.',
            ));
            return;
        }

        $desired = SupportIncludes::parse($value);

        if (!$this->assert($this->resource, $desired)) {
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

        $depth = (string) ($this->maxDepth ?? Definitions::configuration()->maxIncludeDepth);

        return array_merge([$message], array_map(
            fn($failure) => $this->trans(
                sprintf('%s.invalid_includes', self::BASE),
                '":include" doesn\'t have relationship ":relation".',
                $failure,
            ),
            $this->failures,
        ), array_map(
            fn(string $path) => $this->trans(
                sprintf('%s.invalid_depth', self::BASE),
                '":include" is deeper than the maximum include depth of :depth.',
                [':include' => $path, ':depth' => $depth],
            ),
            $this->tooDeep,
        ));
    }

    /**
     * @param array<string, mixed> $desired
     * @param string               $pretend
     *
     * @return bool
     */
    private function assert(string $class, array $desired, string $pretend = ''): bool
    {
        $definition = Definitions::for(RelatedResource::resolve($class));

        $maxDepth = $this->maxDepth ?? Definitions::configuration()->maxIncludeDepth;

        /** @var array<string, mixed> $sub every level of a parsed include is an array */
        foreach ($desired as $relation => $sub) {
            $relationship = $definition->relationship($relation);
            $path = $pretend ? "$pretend.$relation" : $relation;

            if ($maxDepth !== null && substr_count($path, '.') >= $maxDepth) {
                $this->tooDeep[] = $path;
            } elseif ($relationship === null) {
                $this->failures[] = [
                    ':include' => $pretend ?: $definition->type,
                    ':relation' => $relation,
                ];
            } else {
                $this->assert($relationship->related, $sub, $path);
            }
        }

        return $this->failures === [] && $this->tooDeep === [];
    }
}
