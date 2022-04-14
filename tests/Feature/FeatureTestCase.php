<?php

namespace Test\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Test\Support\UseLocalApp;
use Test\TestCase;

abstract class FeatureTestCase extends TestCase
{
    use RefreshDatabase, UseLocalApp;

    public function setUp(): void
    {
        parent::setUp();
        $this->useLocalApp();
    }
}
