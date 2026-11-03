<?php

declare(strict_types=1);

namespace Test\Feature\Laravel;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Test\Feature\FeatureTestCase;

/**
 * The messages of the validation rules are translations of the package, publishable by the application.
 */
final class TranslationsTest extends FeatureTestCase
{
    public function test_the_messages_are_loaded_under_the_jsonapi_namespace(): void
    {
        self::assertSame('The selected :attribute is invalid.', trans('jsonapi::validation.includes.invalid'));
    }

    public function test_the_messages_are_published(): void
    {
        $path = $this->app?->langPath('vendor/jsonapi') ?? '';

        try {
            Artisan::call('vendor:publish', ['--tag' => 'jsonapi-lang']);

            self::assertFileEquals(__DIR__ . '/../../../lang/en/validation.php', "{$path}/en/validation.php");
        } finally {
            (new Filesystem())->deleteDirectory($path);
        }
    }
}
