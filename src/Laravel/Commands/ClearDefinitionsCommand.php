<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Laravel\Commands;

use Illuminate\Console\Command;

/**
 * Deletes `bootstrap/cache/jsonapi.php`.
 */
final class ClearDefinitionsCommand extends Command
{
    /** @var string */
    protected $signature = 'jsonapi:clear';

    /** @var string */
    protected $description = 'Remove the JSON:API resource definitions cache file';

    public function handle(): int
    {
        return self::SUCCESS;
    }
}
