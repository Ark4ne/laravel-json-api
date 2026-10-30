<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Resources\Concerns;

use Ark4ne\JsonApi\Compilation\Definitions;
use Ark4ne\JsonApi\Context\IncludeTree;
use Ark4ne\JsonApi\Context\JsonApiContext;
use Ark4ne\JsonApi\Rendering\Document;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Builds the JSON:API response of a resource or a collection: its document (`toDocument()`), then the top-level members
 * Laravel adds (`with()`, `additional()`), served as `application/vnd.api+json`.
 */
trait ToResponse
{
    /**
     * @var list<string> Relationship paths included whatever the request (`include()`)
     */
    protected array $serverInclude = [];

    /**
     * Includes relationship paths whatever the request, in addition to its `include`: `UserResource::make($user)
     * ->include('posts.comments')`. They are neither validated nor limited by `include.max-depth`.
     *
     * @return $this
     */
    public function include(string ...$paths): static
    {
        $this->serverInclude = [...$this->serverInclude, ...array_values($paths)];

        return $this;
    }

    /**
     * @param Request $request
     */
    public function toResponse($request): JsonResponse
    {
        $document = $this->toDocument($this->renderingContextOf($request))->toArray();

        $response = new JsonResponse(
            array_merge_recursive($document, $this->with($request), $this->additional),
            $this->resource instanceof Model && $this->resource->wasRecentlyCreated ? 201 : 200,
            ['Content-Type' => 'application/vnd.api+json'],
            $this->jsonOptions(),
        );

        $this->withResponse($request, $response);

        return $response;
    }

    abstract protected function toDocument(JsonApiContext $context): Document;

    /**
     * The context of a rendering: the request, parsed, the paths of `include()`, and the configuration set at boot.
     */
    protected function renderingContextOf(mixed $request): JsonApiContext
    {
        $context = JsonApiContext::fromRequest(
            $request instanceof Request ? $request : new Request(),
            Definitions::configuration(),
        );

        return $this->serverInclude === []
            ? $context
            : $context->withInclude(IncludeTree::parse(implode(',', $this->serverInclude)));
    }
}
