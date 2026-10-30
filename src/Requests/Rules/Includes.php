<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Requests\Rules;

use Ark4ne\JsonApi\Compilation\Definitions;
use Ark4ne\JsonApi\Context\IncludeTree;
use Ark4ne\JsonApi\Definition\RelatedResource;
use Ark4ne\JsonApi\Requests\Rules\Traits\UseTrans;
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

        // Parsed as the rendering parses it, every segment kept: the depth is checked here.
        if (!$this->assert([$this->resource], IncludeTree::parse($value))) {
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
     * Checks the relationships included under `$path` against the definitions of `$classes`, then their own includes.
     * Several classes are the related resources of a polymorphic relationship: each relationship needs only one of
     * them to have it, path by path.
     *
     * @param non-empty-list<string> $classes
     */
    private function assert(array $classes, IncludeTree $include, string $path = ''): bool
    {
        $definitions = array_map(
            static fn(string $class) => Definitions::for(RelatedResource::resolve($class)),
            $classes,
        );

        $maxDepth = $this->maxDepth ?? Definitions::configuration()->maxIncludeDepth;

        foreach ($include->children($path) as $relation) {
            $child = $path === '' ? $relation : "{$path}.{$relation}";

            $related = [];
            foreach ($definitions as $definition) {
                $related = [...$related, ...$definition->relationship($relation)->related ?? []];
            }

            if ($maxDepth !== null && substr_count($child, '.') >= $maxDepth) {
                $this->tooDeep[] = $child;
            } elseif ($related === []) {
                $this->failures[] = [
                    ':include' => $path === '' ? $definitions[0]->type : $path,
                    ':relation' => $relation,
                ];
            } else {
                $this->assert(array_values(array_unique($related)), $include, $child);
            }
        }

        return $this->failures === [] && $this->tooDeep === [];
    }
}
