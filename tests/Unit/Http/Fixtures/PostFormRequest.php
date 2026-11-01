<?php

declare(strict_types=1);

namespace Test\Unit\Http\Fixtures;

use Ark4ne\JsonApi\Http\JsonApiFormRequest;
use Test\app\Http\Resources\PostResource;

final class PostFormRequest extends JsonApiFormRequest
{
    protected string $resource = PostResource::class;
}
