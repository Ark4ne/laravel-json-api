<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Http\Rules;

use Ark4ne\JsonApi\Compilation\Definitions;
use Ark4ne\JsonApi\Context\IncludeTree;
use Ark4ne\JsonApi\Definition\RelatedResource;
use Ark4ne\JsonApi\Definition\Revision;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates an include path against the relationships declared by the definitions of the resources it goes through.
 * Nothing is included under a relationship to native resources (`RelationshipDefinition::$native`). Given a revision,
 * every definition is the one of that revision: a client on it includes the relationships it renders, by their names.
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
     * @param Revision|null $revision The revision of the client (`JsonApiContext::revisionOf()`); the head when `null`
     */
    public function __construct(
        private readonly string $resource,
        private readonly ?int $maxDepth = null,
        private readonly ?Revision $revision = null,
    ) {}

    /**
     * The resource (or collection) the values are validated against: public contract, for the tools that document the
     * rule.
     *
     * @return class-string<T>
     */
    public function resource(): string
    {
        return $this->resource;
    }

    /**
     * The revision the values are validated against, `null` for the head: public contract, for the tools that
     * document the rule.
     */
    public function revision(): ?Revision
    {
        return $this->revision;
    }

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
            fn(string $class) => Definitions::for(RelatedResource::resolve($class), $this->revision),
            $classes,
        );

        foreach ($include->children($path) as $relation) {
            $child = $path === '' ? $relation : "{$path}.{$relation}";

            $related = [];
            $native = false;
            foreach ($definitions as $definition) {
                $relationship = $definition->relationship($relation);
                $related = [...$related, ...$relationship->related ?? []];
                $native = $native || ($relationship?->isNative() ?? false);
            }

            if ($maxDepth !== null && substr_count($child, '.') >= $maxDepth) {
                $tooDeep[] = $child;
            } elseif ($related !== []) {
                $this->assert(array_values(array_unique($related)), $include, $child, $maxDepth, $failures, $tooDeep);
            } elseif ($native) {
                foreach ($include->children($child) as $nested) {
                    $failures[] = ['include' => $child, 'relation' => $nested];
                }
            } else {
                $failures[] = ['include' => $path === '' ? $definitions[0]->type : $path, 'relation' => $relation];
            }
        }
    }
}
