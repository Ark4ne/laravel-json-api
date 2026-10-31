<?php

declare(strict_types=1);

namespace Test\Feature\GuessedResources\Fixtures;

use Illuminate\Database\Eloquent\Attributes\UseResource;
use Test\app\Models\User;

#[UseResource(WriterResource::class)]
final class Writer extends User
{
    protected $table = 'users';
}
