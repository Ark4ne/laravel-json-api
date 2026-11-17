<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Laravel\Rendering;

use Ark4ne\JsonApi\Compilation\CompiledDefinitions;
use Ark4ne\JsonApi\Compilation\Definitions;
use Ark4ne\JsonApi\Configuration\SparseRelationships;
use Ark4ne\JsonApi\Context\JsonApiContext;
use Ark4ne\JsonApi\Definition\ResourceDefinition;
use Ark4ne\JsonApi\Definition\RouteNames;
use Ark4ne\JsonApi\Rendering\IncludedRegistry;
use Ark4ne\JsonApi\Rendering\RenderableResource;
use Ark4ne\JsonApi\Rendering\ResourceObject;
use Ark4ne\JsonApi\Rendering\Stage;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Routing\Router;

/**
 * Renders the links of `#[JsonApiRoutes]` with the routes of the application, each route receiving the resource id
 * (its other parameters come from `URL::defaults()`; a relationship route without `{relationship}` in its name also
 * receives the relationship name as its `relationship` parameter):
 *
 * - `links.self` of the resource object;
 * - `links.self` and `links.related` of its relationships, when their route exists: those rendered, and those left out
 *   for having neither data, links nor meta (`relationship.linkage`), unless conditional or not allowed by `fields`.
 *
 * At a revision, the relationships of that revision, by their names at it. The links the resource declares win, even
 * `null`. Put before the stages of `rendering.stages` by the service
 * provider.
 */
final class RouteLinksStage implements Stage
{
    private readonly UrlGenerator $urls;

    private readonly Router $router;

    /** @var array<string, array<string, array{self: string|null, related: string|null}>> The existing routes of the relationships, by resource class and shape (`CompiledDefinitions::key()`) */
    private array $routes = [];

    public function __construct(?UrlGenerator $urls = null, ?Router $router = null)
    {
        $this->urls = $urls ?? app(UrlGenerator::class);
        $this->router = $router ?? app(Router::class);
    }

    public function render(
        ResourceObject $object,
        RenderableResource $resource,
        JsonApiContext $context,
        IncludedRegistry $included,
    ): ResourceObject {
        // The relationships of the revision of the rendering, by their names at it.
        $definition = $resource instanceof JsonApiResource ? Definitions::for($resource::class, $context->revision) : null;
        $routes = $definition?->routes;

        if ($definition === null || $routes === null) {
            return $object;
        }

        if ($routes->self !== null && !array_key_exists('self', $object->links)) {
            $object = $object->withLinks([...$object->links, 'self' => $this->urls->route($routes->self, [$object->id])]);
        }

        $relationships = $this->relationships($object, $definition, $routes, $context);

        return $relationships === null ? $object : $object->withRelationships($relationships);
    }

    /**
     * The relationships with their route links, `null` when none was added.
     *
     * @return array<string, array{data?: array{type: string, id: string, meta?: array<array-key, mixed>}|list<array{type: string, id: string, meta?: array<array-key, mixed>}>|null, links?: array<array-key, mixed>, meta?: array<array-key, mixed>}>|null
     */
    private function relationships(
        ResourceObject $object,
        ResourceDefinition $definition,
        RouteNames $routes,
        JsonApiContext $context,
    ): ?array {
        $relationships = $object->relationships;
        $filter = $context->configuration->sparseRelationships === SparseRelationships::Filter;
        $added = false;

        foreach ($this->existingRoutes($definition, $routes) as $name => $existing) {
            if (!isset($relationships[$name])) {
                // Left out by the relationships stage: its links are rendered unless it was left out on purpose.
                $relationship = $definition->relationships[$name];
                if ($relationship->conditional || $filter && !$context->fields->allows($object->type, $name)) {
                    continue;
                }
            }

            $links = $relationships[$name]['links'] ?? [];

            foreach (['self' => $routes->relationship, 'related' => $routes->related] as $link => $pattern) {
                $route = $existing[$link];
                if ($route !== null && $pattern !== null && !array_key_exists($link, $links)) {
                    $links[$link] = $this->urls->route($route, str_contains($pattern, RouteNames::RELATIONSHIP)
                        ? [$object->id]
                        : [$object->id, 'relationship' => $name]);
                    $added = true;
                }
            }

            if ($links !== []) {
                $relationships[$name]['links'] = $links;
            }
        }

        return $added ? $relationships : null;
    }

    /**
     * The routes of the relationships of a resource class that exist, computed once per class and shape.
     *
     * @return array<string, array{self: string|null, related: string|null}>
     */
    private function existingRoutes(ResourceDefinition $definition, RouteNames $routes): array
    {
        $key = CompiledDefinitions::key($definition);
        if (isset($this->routes[$key])) {
            return $this->routes[$key];
        }

        $existing = [];
        foreach (array_keys($definition->relationships) as $name) {
            $self = $routes->relationshipRoute($name);
            $related = $routes->relatedRoute($name);
            $existing[$name] = [
                'self' => $self !== null && $this->router->has($self) ? $self : null,
                'related' => $related !== null && $this->router->has($related) ? $related : null,
            ];
        }

        return $this->routes[$key] = array_filter(
            $existing,
            static fn(array $routes): bool => $routes['self'] !== null || $routes['related'] !== null,
        );
    }
}
