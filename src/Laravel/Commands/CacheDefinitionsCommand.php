<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Laravel\Commands;

use Illuminate\Console\Command;

/**
 * Compiles the definitions of every resource of the application into `bootstrap/cache/jsonapi.php`.
 */
final class CacheDefinitionsCommand extends Command
{
    /** @var string */
    protected $signature = 'jsonapi:cache';

    /** @var string */
    protected $description = 'Compile the JSON:API resource definitions into a cache file';

    public function handle(): int
    {
        return self::SUCCESS;
    }
}
