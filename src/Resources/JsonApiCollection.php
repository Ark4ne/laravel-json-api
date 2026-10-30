<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Resources;

use Ark4ne\JsonApi\Compilation\Definitions;
use Ark4ne\JsonApi\Context\JsonApiContext;
use Ark4ne\JsonApi\Definition\RelatedResource;
use Ark4ne\JsonApi\Definition\ResourceDefinition;
use Ark4ne\JsonApi\Rendering\Document;
use Ark4ne\JsonApi\Rendering\DocumentBuilder;
use Ark4ne\JsonApi\Rendering\IncludedRegistry;
use Ark4ne\JsonApi\Rendering\RenderableResource;
use Ark4ne\JsonApi\Rendering\ResourceObjectRenderer;
use Ark4ne\JsonApi\Rendering\ValueResolver;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Attributes\Collects;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\AbstractCursorPaginator;
use Illuminate\Pagination\AbstractPaginator;

/**
 * @template T as JsonApiResource
 */
class JsonApiCollection extends ResourceCollection
{
    use Concerns\Relationize;
    use Concerns\ToResponse;

    /**
     * @var class-string<T>
     */
    public $collects;

    /**
     * The definition of the resources this collection collects.
     */
    public static function definition(): ResourceDefinition
    {
        return Definitions::for(RelatedResource::resolve(static::class));
    }

    /**
     * Create a new anonymous resource collection.
     *
     * @param mixed $resource
     * @param null|class-string<T> $collects
     *
     * @return void
     */
    public function __construct($resource, ?string $collects = null)
    {
        $this->collects = $collects ?: $this->collects;

        parent::__construct($resource);
    }

    /**
     * The resource objects of the collected resources; the whole document is built by `toResponse()`.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return list<array{type: string, id: string, attributes?: array<array-key, mixed>, relationships?: array<string, array{data?: array{type: string, id: string, meta?: array<array-key, mixed>}|list<array{type: string, id: string, meta?: array<array-key, mixed>}>|null, links?: array<array-key, mixed>, meta?: array<array-key, mixed>}>, links?: array<array-key, mixed>, meta?: array<array-key, mixed>}>
     */
    public function toArray(mixed $request): array
    {
        $context = $this->renderingContextOf($request);
        $resources = $this->renderableResources($context);
        $renderer = ResourceObjectRenderer::configured($context->configuration);
        $included = new IncludedRegistry($renderer);

        return array_map(
            static fn(RenderableResource $resource) => $renderer->render($resource, $context, $included)->toArray(),
            $resources,
        );
    }

    protected function toDocument(JsonApiContext $context): Document
    {
        $document = (new DocumentBuilder(ResourceObjectRenderer::configured($context->configuration)))->collection($this->renderableResources($context), $context);

        if ($this->resource instanceof AbstractPaginator || $this->resource instanceof AbstractCursorPaginator) {
            $pagination = (new Pagination($this))->information($context->request);

            $document = $document->withLinks($pagination['links'] ?? [])->withMeta($pagination['meta'] ?? []);
        }

        // The meta of the collection win over those of its resources.
        return $document->withMeta(
            (new ValueResolver())->resolve($this->toMeta($context->request) ?? [], $this->resource, $context),
        );
    }

    /**
     * Top-level meta of the document of the collection. They win over the document meta of the collected resources
     * (`JsonApiResource::toMeta()`), merged the first resource first.
     *
     * @see https://jsonapi.org/format/#document-meta
     *
     * @return iterable<string, mixed>|iterable<array-key, \Ark4ne\JsonApi\Descriptors\Values\Value>|null
     */
    protected function toMeta(Request $request): ?iterable
    {
        return null;
    }

    /**
     * The collected resources, their requested relationships eager-loaded once on the whole collection.
     *
     * @return list<RenderableResource>
     */
    private function renderableResources(JsonApiContext $context): array
    {
        $resources = [];
        foreach ($this->collection as $resource) {
            if ($resource instanceof RenderableResource) {
                $resources[] = $resource;
            }
        }

        $first = $resources[0] ?? null;

        if ($first instanceof JsonApiResource) {
            $loads = $first->requestedRelationshipsLoad($context);
            $models = array_map(static fn(RenderableResource $resource) => $resource->renderedModel(), $resources);
            $eloquent = array_filter($models, static fn($model) => $model instanceof Model);

            if ($loads !== [] && count($eloquent) === count($models)) {
                (new Collection($eloquent))->loadMissing($loads);
            }
        }

        return $resources;
    }
}
