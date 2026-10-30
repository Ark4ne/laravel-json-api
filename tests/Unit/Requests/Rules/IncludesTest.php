<?php

declare(strict_types=1);

namespace Test\Unit\Requests\Rules;

use Ark4ne\JsonApi\Compilation\Definitions;
use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Requests\Rules\Includes;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;
use PHPUnit\Framework\Attributes\DataProvider;
use Test\app\Http\Resources\UserCollection;
use Test\app\Http\Resources\UserResource;
use Test\TestCase;
use Test\Unit\Resources\Fixtures\AlphaResource;

class IncludesTest extends TestCase
{
    /**
     * @dataProvider validationDataProvider
     */
    #[DataProvider('validationDataProvider')]
    public function testPasses(mixed $values, array $expected): void
    {
        $rule = new Includes(UserResource::class);
        $errors = [];

        $rule->validate('fields', $values, function ($message) use (&$errors): void {
            $errors[] = $message;
        });
        self::assertEquals($expected, $errors);
    }

    public static function validationDataProvider()
    {
        return [
            // not expected type
            [123, ['The selected :attribute is invalid.']],
            [null, ['The selected :attribute is invalid.']],

            // valid cases
            [implode(',', [
                'posts',
                'posts.user',
                'posts.user.comments',
                'posts.user.posts',
            ]), []],

            // invalid cases
            [implode(',', [
                'posts',
                'unknown',
            ]), [
                'The selected :attribute is invalid.',
                '"user" doesn\'t have relationship "unknown".',
            ]],
            [implode(',', [
                'posts.unknown',
            ]), [
                'The selected :attribute is invalid.',
                '"posts" doesn\'t have relationship "unknown".',
            ]],
        ];
    }

    public function test_the_includes_are_read_from_the_definitions(): void
    {
        self::assertSame([], self::errors(new Includes(AlphaResource::class), 'beta.gamma.alpha.gammas,gammas.lazy'));
        self::assertSame([
            'The selected :attribute is invalid.',
            '"beta.gamma" doesn\'t have relationship "unknown".',
        ], self::errors(new Includes(AlphaResource::class), 'beta.gamma.unknown'));
    }

    public function test_a_collection_validates_the_includes_of_the_resources_it_collects(): void
    {
        self::assertSame([], self::errors(new Includes(UserCollection::class), 'posts.user,comments'));
    }

    /**
     * @return list<string>
     */
    private static function errors(ValidationRule $rule, mixed $value): array
    {
        $errors = [];

        $rule->validate(
            'include',
            $value,
            function (string $message) use (&$errors): PotentiallyTranslatedString {
                $errors[] = $message;

                return new PotentiallyTranslatedString($message, app('translator'));
            },
        );

        return $errors;
    }

    public function test_an_include_deeper_than_the_maximum_depth_fails(): void
    {
        self::assertSame([], self::errors(new Includes(UserResource::class, maxDepth: 2), 'posts.user,comments'));
        self::assertSame([
            'The selected :attribute is invalid.',
            '"posts.user.posts" is deeper than the maximum include depth of 2.',
        ], self::errors(new Includes(UserResource::class, maxDepth: 2), 'posts.user.posts'));
    }

    public function test_the_include_is_parsed_as_the_rendering_parses_it(): void
    {
        self::assertSame([], self::errors(new Includes(UserResource::class), 'posts , comments,,posts.user'));
    }

    public function test_the_maximum_depth_defaults_to_the_configuration(): void
    {
        Definitions::configure(Configuration::fromArray(['include' => ['max-depth' => 1]]));

        try {
            self::assertSame([
                'The selected :attribute is invalid.',
                '"posts.user" is deeper than the maximum include depth of 1.',
            ], self::errors(new Includes(UserResource::class), 'posts.user'));
        } finally {
            Definitions::configure(Configuration::defaults());
        }
    }
}
