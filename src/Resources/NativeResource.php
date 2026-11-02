<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Resources;

use Ark4ne\JsonApi\Context\JsonApiContext;
use Ark4ne\JsonApi\Rendering\RenderableResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiRequest;
use Illuminate\Http\Resources\JsonApi\JsonApiResource as NativeJsonApiResource;
use Symfony\Component\HttpFoundation\InputBag;

/**
 * A related resource of Laravel's native JSON:API, rendered as a leaf (docs/architecture.md §8): its type and id by the
 * native resource, its resource object by its `resolve()`, from a request without `include`. Its `fields[type]` apply;
 * its relationships are neither rendered nor included.
 *
 * @internal Built by `Relationship` for a relationship to native resources.
 */
final class NativeResource implements RenderableResource
{
    /** @var array<array-key, mixed>|null The resource object of the native resource, resolved once */
    private ?array $object = null;

    public function __construct(
        private readonly NativeJsonApiResource $resource,
        private readonly JsonApiRequest $request,
    ) {}

    /**
     * The request native resources are resolved with: the request rendered, without `include`, which the native
     * resource would read from the root of the document.
     */
    public static function requestOf(Request $request): JsonApiRequest
    {
        $native = JsonApiRequest::createFrom($request);
        $native->query->remove('include');
        $native->request->remove('include');
        // `createFrom()` shares the JSON body of the request: it is copied, not changed.
        $native->setJson(new InputBag(array_diff_key($request->json()->all(), ['include' => true])));

        return $native;
    }

    public function renderType(JsonApiContext $context): string
    {
        return $this->resource->resolveResourceType($this->request);
    }

    public function renderIdentifier(JsonApiContext $context): string
    {
        return $this->resource->resolveResourceIdentifier($this->request);
    }

    public function renderedModel(): mixed
    {
        return $this->resource->resource;
    }

    public function renderAttributes(JsonApiContext $context): iterable
    {
        return $this->member('attributes');
    }

    /**
     * None: a native resource is a leaf.
     */
    public function renderRelationships(JsonApiContext $context): iterable
    {
        return [];
    }

    public function renderLinks(JsonApiContext $context): iterable
    {
        return $this->member('links');
    }

    public function renderResourceMeta(JsonApiContext $context): iterable
    {
        return $this->member('meta');
    }

    public function renderDocumentMeta(JsonApiContext $context): iterable
    {
        return [];
    }

    /**
     * A member of the resource object of the native resource, which renders it as an object.
     *
     * @return array<array-key, mixed>
     */
    private function member(string $name): array
    {
        if ($this->object === null) {
            $object = $this->resource->resolve($this->request)['data'] ?? null;
            $this->object = is_array($object) ? $object : [];
        }

        $member = $this->object[$name] ?? [];

        return is_object($member) ? get_object_vars($member) : (is_array($member) ? $member : []);
    }
}
