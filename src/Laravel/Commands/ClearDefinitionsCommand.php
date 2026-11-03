<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Laravel\Commands;

use Ark4ne\JsonApi\Compilation\CompiledDefinitions;
use Ark4ne\JsonApi\Compilation\Definitions;
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

    public function handle(CompiledDefinitions $compiled): int
    {
        $compiled->delete();
        Definitions::flush();

        $this->components->info('JSON:API definitions cache cleared successfully.');

        return self::SUCCESS;
    }
}
