<?php

namespace Test\app\Http\Controllers;

use Ark4ne\JsonApi\Resources\JsonApiCollection;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Routing\Controller;
use Test\app\Http\Requests\UserRequest;
use Test\app\Http\Resources\UserResource;
use Test\app\Models\User;

class UserController extends Controller
{
    use AsApiController {
        index as apiIndex;
        show as apiShow;
    }

    protected function getModelClass(): string
    {
        return User::class;
    }

    protected function getResourceClass(): string
    {
        return UserResource::class;
    }

    public function index(UserRequest $request): JsonApiCollection
    {
        return $this->apiIndex($request);
    }

    public function show(UserRequest $request, string $id): JsonApiResource
    {
        return $this->apiShow($request, $id);
    }
}
