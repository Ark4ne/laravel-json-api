<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Context;

/**
 * The relationship paths of the `include` query parameter, parsed once per request.
 *
 * A path implies its parents: `posts.comments` includes `posts` too, as the specification requires (full linkage).
 */
final readonly class IncludeTree
{
    /**
     * @param list<string> $paths Every requested path, parents included, in request order
     * @param array<string, list<string>> $children The relationships included directly under each path, `''` for the
     *                                              primary resources
     */
    private function __construct(
        private array $paths,
        private array $children,
    ) {}

    /**
     * Parses `include` (`posts,posts.comments`): spaces and empty segments are ignored, and a value that is not a string
     * includes nothing (its validation is the job of `Rules\Includes`).
     *
     * @param positive-int|null $maxDepth Segments deeper are ignored (`include.max-depth`): nothing is loaded nor
     *                                    rendered beyond, even when the request is not validated
     */
    public static function parse(mixed $include, ?int $maxDepth = null): self
    {
        if (!is_string($include)) {
            return self::none();
        }

        $paths = [];
        $children = [];
        foreach (explode(',', $include) as $path) {
            $parent = '';
            $depth = 0;
            foreach (explode('.', $path) as $segment) {
                $segment = trim($segment);

                if ($segment === '') {
                    continue;
                }

                if ($maxDepth !== null && ++$depth > $maxDepth) {
                    break;
                }

                $current = $parent === '' ? $segment : "{$parent}.{$segment}";

                // A numeric path would become an integer key: the paths are kept in a list, the keys only index them.
                if (!isset($children[$current])) {
                    $paths[] = $current;
                    $children[$current] = [];
                    $children[$parent][] = $segment;
                }

                $parent = $current;
            }
        }

        return new self($paths, $children);
    }

    public static function none(): self
    {
        return new self([], []);
    }

    /**
     * Whether the path (`posts.comments`) is included.
     */
    public function has(string $path): bool
    {
        return $path !== '' && isset($this->children[$path]);
    }

    /**
     * The relationships included directly under a path; under the primary resources for the empty path.
     *
     * @return list<string>
     */
    public function children(string $path = ''): array
    {
        return $this->children[$path] ?? [];
    }

    /**
     * @return list<string>
     */
    public function paths(): array
    {
        return $this->paths;
    }

    public function isEmpty(): bool
    {
        return $this->paths === [];
    }
}
