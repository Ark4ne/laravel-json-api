<?php

declare(strict_types=1);

namespace Test\Unit\Laravel\Fixtures\Nested;

use Ark4ne\JsonApi\Resources\JsonApiResource;

/**
 * @extends JsonApiResource<\Test\app\Models\Post>
 */
final class NestedResource extends JsonApiResource {}
