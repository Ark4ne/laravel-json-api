<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Laravel;

use Ark4ne\JsonApi\Compilation\CompiledDefinitions;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the package into a Laravel application; replaces `Providers\LaravelJsonApiProvider`.
 *
 * - Publishes and merges `config/jsonapi.php`, builds the `Configuration` and gives it to `Definitions`.
 * - Gives `bootstrap/cache/jsonapi.php` to `Definitions`, which reads it on first use: nothing is compiled nor read at
 *   boot.
 * - Registers `jsonapi:cache`, `jsonapi:clear` and `jsonapi:check`; `php artisan optimize` caches the definitions.
 */
final class JsonApiServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void {}

    /**
     * The file of compiled definitions of the application.
     */
    public function compiledDefinitions(): CompiledDefinitions
    {
        return new CompiledDefinitions($this->app->bootstrapPath('cache/jsonapi.php'));
    }
}
