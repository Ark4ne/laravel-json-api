<?php

namespace Ark4ne\JsonApi\Providers;

use Ark4ne\JsonApi\Support\Config;
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
    }
}
