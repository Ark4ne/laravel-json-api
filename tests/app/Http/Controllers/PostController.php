<?php

namespace Test\app\Http\Controllers;

use Ark4ne\JsonApi\Resources\JsonApiCollection;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Routing\Controller;
use Test\app\Http\Requests\PostRequest;
use Test\app\Http\Resources\PostResource;
use Test\app\Models\Post;

class PostController extends Controller
{
    use AsApiController {
        index as apiIndex;
        show as apiShow;
    }

    protected function getModelClass(): string
    {
        return Post::class;
    }

    protected function getResourceClass(): string
    {
        return PostResource::class;
    }

    public function index(PostRequest $request): JsonApiCollection
    {
        return $this->apiIndex($request);
    }

    public function show(PostRequest $request, string $id): JsonApiResource
    {
        return $this->apiShow($request, $id);
    }
}
