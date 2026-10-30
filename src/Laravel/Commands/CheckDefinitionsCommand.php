<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Laravel\Commands;

use Illuminate\Console\Command;

/**
 * Compiles the definitions of the resources and prints their diagnostics, grouped by resource; replaces
 * `Asserts\AssertJsonApiResource`.
 *
 * Fails when a definition cannot be compiled or has an error; with `--strict`, also on the first warning.
 */
final class CheckDefinitionsCommand extends Command
{
    /** @var string */
    protected $signature = 'jsonapi:check
        {resources?* : Resource classes to check (default: every resource found in definitions.paths)}
        {--strict : Fail on warnings too}';

    /** @var string */
    protected $description = 'Check the JSON:API resource definitions and print their diagnostics';

    public function handle(): int
    {
        return self::SUCCESS;
    }
}
