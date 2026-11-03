<?php

declare(strict_types=1);

namespace Test\app\Http\Requests;

use Ark4ne\JsonApi\Http\JsonApiFormRequest;
use Test\app\Http\Resources\PostResource;

class PostRequest extends JsonApiFormRequest
{
    protected string $resource = PostResource::class;
}
