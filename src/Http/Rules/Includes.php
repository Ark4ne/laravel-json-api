<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Http\Rules;

use Ark4ne\JsonApi\Compilation\Definitions;
use Ark4ne\JsonApi\Context\IncludeTree;
use Ark4ne\JsonApi\Definition\RelatedResource;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates an include path against the relationships declared by the definitions of the resources it goes through.
 *
 * Its messages are the translations `jsonapi::validation.includes.*` (`vendor:publish --tag=jsonapi-lang`).
 *
 * @template T as \Ark4ne\JsonApi\Resources\JsonApiResource|\Ark4ne\JsonApi\Resources\JsonApiCollection
 */
final class Includes implements ValidationRule
{
    /**
     * @param class-string<T> $resource
     * @param positive-int|null $maxDepth Deepest include path accepted; the configured one (`include.max-depth`) when
     *                                    `null`
     */
    public function __construct(
        private readonly string $resource,
        private readonly ?int $maxDepth = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value)) {
            $fail('jsonapi::validation.includes.invalid')->translate();

            return;
        }

        $maxDepth = $this->maxDepth ?? Definitions::configuration()->maxIncludeDepth;
        $failures = [];
        $tooDeep = [];

        // Parsed as the rendering parses it, every segment kept: the depth is checked here.
        $this->assert([$this->resource], IncludeTree::parse($value), '', $maxDepth, $failures, $tooDeep);

        if ($failures === [] && $tooDeep === []) {
            return;
        }

        $fail('jsonapi::validation.includes.invalid')->translate();
        foreach ($failures as $failure) {
            $fail('jsonapi::validation.includes.invalid_includes')->translate($failure);
        }
        foreach ($tooDeep as $path) {
            $fail('jsonapi::validation.includes.invalid_depth')->translate([
                'include' => $path,
                'depth' => (string) $maxDepth,
            ]);
        }
    }

    /**
     * Checks the relationships included under `$path` against the definitions of `$classes`, then their own includes.
     * Several classes are the related resources of a polymorphic relationship: each relationship needs only one of
     * them to have it, path by path.
     *
     * @param non-empty-list<string> $classes
     * @param list<array{include: string, relation: string}> $failures The relationships that do not exist
     * @param list<string> $tooDeep The include paths deeper than the maximum depth
     */
    private function assert(
        array $classes,
        IncludeTree $include,
        string $path,
        ?int $maxDepth,
        array &$failures,
        array &$tooDeep,
    ): void {
        $definitions = array_map(
            static fn(string $class) => Definitions::for(RelatedResource::resolve($class)),
            $classes,
        );

        foreach ($include->children($path) as $relation) {
            $child = $path === '' ? $relation : "{$path}.{$relation}";

            $related = [];
            foreach ($definitions as $definition) {
                $related = [...$related, ...$definition->relationship($relation)->related ?? []];
            }

            if ($maxDepth !== null && substr_count($child, '.') >= $maxDepth) {
                $tooDeep[] = $child;
            } elseif ($related === []) {
                $failures[] = ['include' => $path === '' ? $definitions[0]->type : $path, 'relation' => $relation];
            } else {
                $this->assert(array_values(array_unique($related)), $include, $child, $maxDepth, $failures, $tooDeep);
            }
        }
    }
}
