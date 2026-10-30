<?php

declare(strict_types=1);

namespace Benchmark\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 */
final class User extends Model
{
    protected $guarded = [];
}
