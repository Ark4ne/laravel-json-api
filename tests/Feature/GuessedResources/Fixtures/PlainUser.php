<?php

declare(strict_types=1);

namespace Test\Feature\GuessedResources\Fixtures;

use Illuminate\Database\Eloquent\Attributes\UseResource;
use Test\app\Models\User;

/**
 * Declares a resource that is not a JSON:API resource.
 */
#[UseResource(PlainUserResource::class)]
final class PlainUser extends User
{
    protected $table = 'users';
}
