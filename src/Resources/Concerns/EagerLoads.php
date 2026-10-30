<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Resources\Concerns;

use Ark4ne\JsonApi\Compilation\Definitions;
use Ark4ne\JsonApi\Context\JsonApiContext;
use Ark4ne\JsonApi\Definition\DefinitionSource;
use Ark4ne\JsonApi\Definition\RelatedResource;
use Closure;

/**
 * The eager-loads of `JsonApiResource`, read from the definitions (`withLoad()`), until the loading layer (roadmap E5)
 * replaces it.
 */
trait EagerLoads
{
    /**
     * The Eloquent relations to eager-load for the requested includes, read from the definitions: a constrained path
     * gives its closure, any other path an empty array.
     *
     * @return array<string, Closure|array{}>
     * @internal
     */
    public function requestedRelationshipsLoad(JsonApiContext $context): array
    {
        $loads = $this->requestedLoadsOf($context, static::class);

        // A path loaded without constraint is implied by the deeper paths it prefixes.
        return array_filter(
            $loads,
            static fn($load, string $path) => $load !== [] || !self::prefixesAny($path, array_keys($loads)),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /**
     * @param class-string<DefinitionSource> $class
     *
     * @return array<string, Closure|array{}>
     */
    private function requestedLoadsOf(JsonApiContext $context, string $class): array
    {
        $loads = [];
        $constraints = Definitions::loadConstraints($class);

        foreach (Definitions::for($class)->relationships as $name => $relationship) {
            if ($relationship->load->isEmpty() || !$context->includes($name)) {
                continue;
            }

            $nested = $this->requestedLoadsOf($context->withPath($name), RelatedResource::resolve($relationship->related));

            $paths = $relationship->load->paths;

            foreach ($paths as $path) {
                $loads[$path] = $constraints[$name][$path] ?? [];

                // The related resources are reached through the roots of the load, not through the paths below.
                $root = array_filter($paths, static fn(string $other) => self::prefixesAny($other, [$path])) === [];

                if ($root) {
                    foreach ($nested as $sub => $load) {
                        $loads["$path.$sub"] = $load;
                    }
                }
            }
        }

        return $loads;
    }

    /**
     * Whether `$path` is the parent of one of `$paths` (`comments` of `comments.user`).
     *
     * @param array<array-key, string> $paths
     */
    private static function prefixesAny(string $path, array $paths): bool
    {
        foreach ($paths as $other) {
            if (str_starts_with($other, "$path.")) {
                return true;
            }
        }

        return false;
    }
}
