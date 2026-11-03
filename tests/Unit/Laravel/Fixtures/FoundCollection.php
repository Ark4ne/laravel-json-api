<?php

declare(strict_types=1);

namespace Test\Unit\Laravel\Fixtures;

use Ark4ne\JsonApi\Resources\JsonApiCollection;

/**
 * @extends JsonApiCollection<FoundResource>
 */
final class FoundCollection extends JsonApiCollection
{
    public $collects = FoundResource::class;
}
