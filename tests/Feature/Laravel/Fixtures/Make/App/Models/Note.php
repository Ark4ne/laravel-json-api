<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A model of the application of Testbench (`App\`), found by the convention: loaded by the test, not autoloaded.
 */
final class Note extends Model
{
    public $timestamps = false;

    /** @var array<string, string> */
    protected $casts = ['pinned' => 'bool'];
}
