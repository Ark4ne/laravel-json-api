<?php

declare(strict_types=1);

namespace Test\app\Http\Requests;

use Ark4ne\JsonApi\Http\JsonApiFormRequest;
use Test\app\Http\Resources\UserResource;

class UserRequest extends JsonApiFormRequest
{
    protected string $resource = UserResource::class;
}
