<?php

namespace Ark4ne\JsonApi\Providers;

use Ark4ne\JsonApi\Support\Config;
use Ark4ne\JsonApi\Support\Fields;
use Ark4ne\JsonApi\Support\Includes;
use Illuminate\Support\ServiceProvider;

class LaravelJsonApiProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->publishes([
            __DIR__ . '/../../config/jsonapi.php' => config_path('jsonapi.php')
        ], 'config');
    }

    public function boot(): void
    {
        Config::boot();

        if (class_exists(\Laravel\Octane\Events\RequestReceived::class)) {
            $this->app['events']->listen(
                \Laravel\Octane\Events\RequestReceived::class,
                static function () {
                    Fields::flush();
                    Includes::flush();
                }
            );
        }
    }
}
