<?php

declare(strict_types=1);

namespace Test\app\Http\Resources;

use Ark4ne\JsonApi\Resources\JsonApiCollection;

/**
 * @extends JsonApiCollection<UserResource>
 */
class UserCollection extends JsonApiCollection
{
    public $collects = UserResource::class;
}
