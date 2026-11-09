<?php

declare(strict_types=1);

namespace Test\app\Http\Requests\Revisions;

use Ark4ne\JsonApi\Http\JsonApiFormRequest;
use Test\app\Http\Resources\Revisions\PostResource;

/**
 * The query of the posts of the revisions scenario, validated at the revision of the client.
 */
final class PostRequest extends JsonApiFormRequest
{
    protected string $resource = PostResource::class;
}
