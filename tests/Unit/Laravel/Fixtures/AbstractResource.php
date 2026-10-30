<?php

declare(strict_types=1);

namespace Test\Unit\Laravel\Fixtures;

use Ark4ne\JsonApi\Resources\JsonApiResource;

/**
 * @extends JsonApiResource<\Test\app\Models\Post>
 */
abstract class AbstractResource extends JsonApiResource {}
