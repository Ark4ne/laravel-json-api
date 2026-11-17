<?php

declare(strict_types=1);

namespace Test\Unit\Compilation\Fixtures;

use Ark4ne\JsonApi\Definition\JsonApiSince;
use Ark4ne\JsonApi\Definition\JsonApiUntil;
use Ark4ne\JsonApi\Resources\Attributes\JsonApiType;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Test\app\Models\User;
use Test\app\Revisions\ApiRevision;

/**
 * Exists since a revision newer than the one it exists until: at no revision.
 *
 * @extends JsonApiResource<User>
 */
#[JsonApiType('nowhere')]
#[JsonApiSince(ApiRevision::UsersReplaceTeachers)]
#[JsonApiUntil(ApiRevision::Initial)]
final class EmptyLifetimeResource extends JsonApiResource {}
