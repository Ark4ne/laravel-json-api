<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Definition;

use Closure;

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
     * The load declared with `withLoad()` by the relationship `$name`: `true` loads the relationship itself, a string or a
     * list loads relation paths, and a closure under a path constrains it (`['comments' => fn($query) => …]`). Nested
     * arrays give dotted paths (`['comments' => ['user']]` loads `comments.user`).
     *
     * @param bool|string|array<array-key, mixed> $load
     *
     * @internal
     */
    public static function fromDeclaration(bool|string|array $load, string $name): self
    {
        if ($load === false) {
            return self::none();
        }

        $paths = [];
        $constraints = [];
        self::collect($load === true ? $name : $load, null, $paths, $constraints);

        return self::of($paths, array_keys($constraints));
    }

    /**
     * The constraint closures of the load declared with `withLoad()`, by relation path: what the definition leaves out
     * because closures are not serializable.
     *
     * @param bool|string|array<array-key, mixed> $load
     *
     * @return array<string, Closure>
     *
     * @internal
     */
    public static function constraintsOf(bool|string|array $load): array
    {
        $paths = [];
        $constraints = [];

        if (is_array($load)) {
            self::collect($load, null, $paths, $constraints);
        }

        return $constraints;
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

    /**
     * @param string|array<array-key, mixed> $load
     * @param list<string> $paths
     * @param array<string, Closure> $constraints
     */
    private static function collect(string|array $load, ?string $prefix, array &$paths, array &$constraints): void
    {
        foreach ((array) $load as $key => $value) {
            if (is_string($value)) {
                $paths[] = $prefix === null ? $value : "{$prefix}.{$value}";

                continue;
            }

            if (!is_string($key)) {
                continue;
            }

            $path = $prefix === null ? $key : "{$prefix}.{$key}";

            $paths[] = $path;

            if (is_array($value)) {
                self::collect($value, $path, $paths, $constraints);
            } elseif ($value instanceof Closure) {
                $constraints[$path] = $value;
            }
        }
    }
}
