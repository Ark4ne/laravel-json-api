<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Resources;

use Ark4ne\JsonApi\Context\JsonApiContext;
use Ark4ne\JsonApi\Definition\DefinitionSource;
use Ark4ne\JsonApi\Descriptors;
use Ark4ne\JsonApi\Rendering\Document;
use Ark4ne\JsonApi\Rendering\DocumentBuilder;
use Ark4ne\JsonApi\Rendering\IncludedRegistry;
use Ark4ne\JsonApi\Rendering\RenderableResource;
use Ark4ne\JsonApi\Rendering\ResourceObjectRenderer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @template T
 * @property T $resource
 */
abstract class JsonApiResource extends JsonResource implements DefinitionSource, RenderableResource
{
    use Descriptors\Relations;
    use Descriptors\Values;
    use Concerns\Relationize;
    use Concerns\Identifier;
    use Concerns\Attributes;
    use Concerns\ConditionallyLoadsAttributes;
    use Concerns\Relationships;
    use Concerns\EagerLoads;
    use Concerns\Links;
    use Concerns\Meta;
    use Concerns\Definable;
    use Concerns\Renderable;
    use Concerns\ToResponse;

    /** @var T */
    public $resource;

    /**
     * The resource object of this resource (https://jsonapi.org/format/#document-resource-objects); the whole document
     * is built by `toResponse()`.
     *
     * @param Request $request
     *
     * @return array{type: string, id: string, attributes?: array<array-key, mixed>, relationships?: array<string, array{data?: array{type: string, id: string, meta?: array<array-key, mixed>}|list<array{type: string, id: string, meta?: array<array-key, mixed>}>|null, links?: array<array-key, mixed>, meta?: array<array-key, mixed>}>, links?: array<array-key, mixed>, meta?: array<array-key, mixed>}
     */
    public function toArray(mixed $request): array
    {
        $context = $this->renderingContextOf($request);
        $this->loadRequestedRelationships($context);

        $renderer = ResourceObjectRenderer::configured($context->configuration);

        return $renderer->render($this, $context, new IncludedRegistry($renderer))->toArray();
    }

    /**
     * The collection of the resources. Override it to return a collection class of your own (`UserCollection`). `data`
     * is always a JSON list: `$preserveKeys` does not apply.
     *
     * @param mixed $resource
     *
     * @return JsonApiCollection<static>
     */
    public static function collection($resource): JsonApiCollection
    {
        return new JsonApiCollection($resource, static::class);
    }

    protected function toDocument(JsonApiContext $context): Document
    {
        $this->loadRequestedRelationships($context);

        return (new DocumentBuilder(ResourceObjectRenderer::configured($context->configuration)))
            ->resource($this, $context);
    }

    private function loadRequestedRelationships(JsonApiContext $context): void
    {
        $loads = $this->requestedRelationshipsLoad($context);

        if ($loads !== [] && $this->resource instanceof Model) {
            $this->resource->loadMissing($loads);
        }
    }
}
