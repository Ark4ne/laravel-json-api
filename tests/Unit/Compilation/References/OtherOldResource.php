<?php

declare(strict_types=1);

namespace Test\Unit\Compilation\References;

use Ark4ne\JsonApi\Definition\JsonApiUntil;
use Ark4ne\JsonApi\Resources\Attributes\JsonApiType;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Test\app\Revisions\OtherRevision;

/**
 * A resource up to the first case of the second enum.
 *
 * @extends JsonApiResource<object>
 */
#[JsonApiType('other-old')]
#[JsonApiUntil(OtherRevision::First)]
final class OtherOldResource extends JsonApiResource {}
