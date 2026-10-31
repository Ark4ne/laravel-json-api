<?php

declare(strict_types=1);

namespace Test\Feature\GuessedResources\Fixtures;

use Ark4ne\JsonApi\Resources\Attributes\JsonApiModel;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use stdClass;

/**
 * @extends JsonApiResource<stdClass>
 */
#[JsonApiModel(stdClass::class)]
final class NotEloquentResource extends JsonApiResource {}
