<?php

declare(strict_types=1);

namespace Test;

use Ark4ne\JsonApi\Laravel\JsonApiServiceProvider;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Translation\PotentiallyTranslatedString;
use Orchestra\Testbench\TestCase as BaseTestCase;

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
