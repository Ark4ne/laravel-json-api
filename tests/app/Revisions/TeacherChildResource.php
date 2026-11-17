<?php

declare(strict_types=1);

namespace Test\app\Revisions;

use Ark4ne\JsonApi\Resources\Attributes\JsonApiType;

/**
 * A subclass of a resource with a lifetime, declaring none.
 */
#[JsonApiType('teacher-children')]
final class TeacherChildResource extends TeacherResource {}
