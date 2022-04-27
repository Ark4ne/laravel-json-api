<?php

namespace Test\app\Http\Controllers;

use Ark4ne\JsonApi\Resources\JsonApiCollection;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Routing\Controller;
use Test\app\Http\Requests\CommentRequest;
use Test\app\Http\Resources\CommentResource;
use Test\app\Models\Comment;

class CommentController extends Controller
{
    use AsApiController {
        index as apiIndex;
        show as apiShow;
    }

    protected function getModelClass(): string
    {
        return Comment::class;
    }

    protected function getResourceClass(): string
    {
        return CommentResource::class;
    }

    public function index(CommentRequest $request): JsonApiCollection
    {
        return $this->apiIndex($request);
    }

    public function show(CommentRequest $request, string $id): JsonApiResource
    {
        return $this->apiShow($request, $id);
    }
}
