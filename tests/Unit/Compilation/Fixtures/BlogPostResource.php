<?php

declare(strict_types=1);

namespace Test\Unit\Compilation\Fixtures;

use Ark4ne\JsonApi\Resources\JsonApiResource;

/**
 * Declares no type: it gets that of the configured naming convention.
 *
 * @extends JsonApiResource<mixed>
 */
final class BlogPostResource extends JsonApiResource {}
