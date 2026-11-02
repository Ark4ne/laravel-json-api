<?php

declare(strict_types=1);

namespace Test\Feature\GuessedResources\Fixtures;

use Illuminate\Database\Eloquent\Attributes\UseResource;
use Test\app\Http\Resources\NativeUserResource;
use Test\app\Models\User;

#[UseResource(NativeUserResource::class)]
final class NativeWriter extends User
{
    protected $table = 'users';
}
