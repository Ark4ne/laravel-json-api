<?php

declare(strict_types=1);

namespace Test;

use Ark4ne\ApiRevisions\Laravel\Http\Resolvers\HeaderResolver;
use Ark4ne\ApiRevisions\Revisions\Revision;
use Ark4ne\ApiRevisions\Revisions\RevisionScope;
use Ark4ne\JsonApi\Laravel\JsonApiServiceProvider;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Translation\PotentiallyTranslatedString;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Test\app\Revisions\ApiRevision;

class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            JsonApiServiceProvider::class,
        ];
    }

    /**
     * The test application declares its revisions with `ApiRevision`, read from the `Api-Version` header by the
     * `revision` middleware of `ark4ne/laravel-api-revisions`.
     */
    protected function defineEnvironment($app): void
    {
        // What a test sets beforehand (`#[DefineEnvironment]` runs first) wins: another enum, or `withoutRevisions()`.
        if ($app['config']->get('testing.unrevisioned') !== true) {
            $app['config']->set('revisions.enum', $app['config']->get('revisions.enum') ?? ApiRevision::class);
        }
        $app['config']->set('revisions.resolvers', [HeaderResolver::class => ['name' => 'Api-Version']]);
    }

    /**
     * For `#[DefineEnvironment('withoutRevisions')]`: an application that configures no revision enum.
     *
     * @param \Illuminate\Foundation\Application $app
     */
    protected function withoutRevisions($app): void
    {
        $app['config']->set('testing.unrevisioned', true);
    }

    /**
     * Makes `$revision` the current revision of this test, as the `revision` middleware does for a request; `null`
     * for the head.
     */
    protected static function useRevision(?Revision $revision): void
    {
        RevisionScope::get()->setBase($revision);
    }

    /**
     * The messages a validation rule fails with for a value, as the validator reads them: translated when the rule
     * asked for it.
     *
     * @return list<string>
     */
    protected static function ruleErrors(string $attribute, ValidationRule $rule, mixed $value): array
    {
        /** @var list<PotentiallyTranslatedString> $messages */
        $messages = [];

        $rule->validate(
            $attribute,
            $value,
            function (string $message) use (&$messages): PotentiallyTranslatedString {
                return $messages[] = new PotentiallyTranslatedString($message, app('translator'));
            },
        );

        return array_map(static fn(PotentiallyTranslatedString $message): string => (string) $message, $messages);
    }
}
