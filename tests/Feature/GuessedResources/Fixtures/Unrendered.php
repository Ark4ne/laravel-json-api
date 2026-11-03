<?php

declare(strict_types=1);

namespace Test\Feature\GuessedResources\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * A model without resource: neither `#[UseResource]` nor a resource by convention.
 */
final class Unrendered extends Model
{
    protected $table = 'users';
}
