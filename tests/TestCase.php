<?php

namespace Test;

use Ark4ne\JsonApi\Providers\LaravelJsonApiProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            LaravelJsonApiProvider::class,
        ];
    }
}
