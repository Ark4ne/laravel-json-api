<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Laravel;

use Ark4ne\JsonApi\Compilation\CompiledDefinitions;
use Ark4ne\JsonApi\Compilation\Definitions;
use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Context\JsonApiContext;
use Ark4ne\JsonApi\Laravel\Commands\CacheDefinitionsCommand;
use Ark4ne\JsonApi\Laravel\Commands\CheckDefinitionsCommand;
use Ark4ne\JsonApi\Laravel\Commands\ClearDefinitionsCommand;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use WeakMap;

/**
 * Wires the package into a Laravel application; replaces `Providers\LaravelJsonApiProvider`.
 *
 * - Publishes and merges `config/jsonapi.php`, builds the `Configuration` and gives it to `Definitions`.
 * - Gives `bootstrap/cache/jsonapi.php` to `Definitions`, which reads it on first use: nothing is compiled nor read at
 *   boot.
 * - Binds one `JsonApiContext` per request object, built from it on first use.
 * - Registers `jsonapi:cache`, `jsonapi:clear` and `jsonapi:check`; `php artisan optimize` caches the definitions.
 */
final class JsonApiServiceProvider extends ServiceProvider
{
    private const CONFIG = __DIR__ . '/../../config/jsonapi.php';

    public function register(): void
    {
        $this->mergeConfigFrom(self::CONFIG, 'jsonapi');

        $this->app->singleton(Configuration::class, static function (Application $app): Configuration {
            /** @var array<array-key, mixed> $jsonapi */
            $jsonapi = $app->make('config')->get('jsonapi', []);
            /** @var array<array-key, mixed> $package */
            $package = require self::CONFIG;

            // `mergeConfigFrom()` merges the top-level keys only: a file published before a nested key existed
            // (`describer.strict`) takes the default of the package for it.
            return Configuration::fromArray(array_replace_recursive($package, $jsonapi));
        });

        $this->app->singleton(CompiledDefinitions::class, fn(): CompiledDefinitions => $this->compiledDefinitions());

        $this->app->singleton(ResourceFinder::class, static function (Application $app): ResourceFinder {
            /** @var Configuration $configuration */
            $configuration = $app->make(Configuration::class);

            return new ResourceFinder($configuration->resourcePaths !== [] ? $configuration->resourcePaths : [app_path()]);
        });

        // One context per request object, built on first use: a new request (HTTP, Octane, test) gets its own, and the
        // weak map lets the old ones go with their request.
        /** @var WeakMap<Request, JsonApiContext> $contexts */
        $contexts = new WeakMap();
        $this->app->bind(JsonApiContext::class, static function (Application $app) use ($contexts): JsonApiContext {
            /** @var Request $request */
            $request = $app->make('request');
            /** @var Configuration $configuration */
            $configuration = $app->make(Configuration::class);

            return $contexts[$request] ??= JsonApiContext::fromRequest($request, $configuration);
        });
    }

    public function boot(): void
    {
        /** @var Configuration $configuration */
        $configuration = $this->app->make(Configuration::class);
        /** @var CompiledDefinitions $compiled */
        $compiled = $this->app->make(CompiledDefinitions::class);

        Definitions::configure($configuration, $compiled);

        $this->publishes([self::CONFIG => $this->app->configPath('jsonapi.php')], 'config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                CacheDefinitionsCommand::class,
                ClearDefinitionsCommand::class,
                CheckDefinitionsCommand::class,
            ]);
            $this->optimizes(optimize: 'jsonapi:cache', clear: 'jsonapi:clear', key: 'jsonapi');
        }
    }

    /**
     * The file of compiled definitions of the application.
     */
    public function compiledDefinitions(): CompiledDefinitions
    {
        return new CompiledDefinitions($this->app->bootstrapPath('cache/jsonapi.php'));
    }
}
