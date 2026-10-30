<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Definition;

/**
 * The Eloquent relations a relationship eager-loads when it is included (`withLoad()`).
 *
 * Constraints are closures and stay out of the definition, which must remain serializable: only the paths they
 * constrain are recorded, and the renderer reads the closures from the resource at runtime.
 */
final readonly class LoadDefinition
{
    /**
     * @param list<string> $paths Relation paths relative to the resource (`comments`, `comments.author`)
     * @param list<string> $constrained The paths among `$paths` loaded with a constraint closure
     */
    private function __construct(
        public array $paths,
        public array $constrained,
    ) {}

    /**
     * The relationship loads nothing (`withLoad(false)`, the default).
     */
    public static function none(): self
    {
        return new self([], []);
    }

    /**
     * @param list<string> $paths
     * @param list<string> $constrained
     */
    public static function of(array $paths, array $constrained = []): self
    {
        return new self(
            array_values(array_unique($paths)),
            array_values(array_intersect(array_unique($constrained), $paths)),
        );
    }

    public function isEmpty(): bool
    {
        return $this->paths === [];
    }

    /**
     * Rebuilds a definition exported with `var_export()` (compiled definitions).
     *
     * @param array{paths: list<string>, constrained: list<string>} $properties
     */
    public static function __set_state(array $properties): self
    {
        return new self($properties['paths'], $properties['constrained']);
    }
}
