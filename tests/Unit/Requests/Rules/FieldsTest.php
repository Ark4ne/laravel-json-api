<?php

declare(strict_types=1);

namespace Test\Unit\Requests\Rules;

use Ark4ne\JsonApi\Requests\Rules\Fields;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;
use PHPUnit\Framework\Attributes\DataProvider;
use Test\app\Http\Resources\UserCollection;
use Test\app\Http\Resources\UserResource;
use Test\TestCase;
use Test\Unit\Resources\Fixtures\AlphaResource;

class FieldsTest extends TestCase
{
    /**
     * @dataProvider validationDataProvider
     */
    #[DataProvider('validationDataProvider')]
    public function testPasses(mixed $values, array $expected): void
    {
        $rule = new Fields(UserResource::class);
        $errors = [];

        $rule->validate('fields', $values, function ($message) use (&$errors): void {
            $errors[] = $message;
        });
        $this->assertEquals($expected, $errors);
    }

    public static function validationDataProvider()
    {
        return [
            // not expected type
            ['string', ['The selected :attribute is invalid.']],
            [123, ['The selected :attribute is invalid.']],
            [null, ['The selected :attribute is invalid.']],

            // valid cases
            [['user' => 'name,email'], []],
            [['user' => 'name,email', 'post' => 'content'], []],

            // invalid cases
            [['user' => 'name,email', 'unknown' => 'content'], [
                'The selected :attribute is invalid.',
                '"unknown" doesn\'t exists.',
            ]],
            [['user' => 'name,unknown', 'post' => 'content'], [
                'The selected :attribute is invalid.',
                '"user" doesn\'t have fields "unknown".',
            ]],
            [['user' => 'name,email', 'post' => 'unknown'], [
                'The selected :attribute is invalid.',
                '"post" doesn\'t have fields "unknown".',
            ]],
        ];
    }

    public function test_relationship_names_are_valid_fields(): void
    {
        self::assertSame([], self::errors(new Fields(UserResource::class), ['user' => 'name,posts', 'post' => 'title,user']));
    }

    public function test_the_fields_are_read_from_the_definitions(): void
    {
        self::assertSame([], self::errors(new Fields(AlphaResource::class), [
            'alpha' => 'title,size,size.width,beta,gammas',
            'beta' => 'name,gamma',
            'gamma' => 'label,alpha',
        ]));
        self::assertSame([
            'The selected :attribute is invalid.',
            '"gamma" doesn\'t have fields "name".',
            '"unknown" doesn\'t exists.',
        ], self::errors(new Fields(AlphaResource::class), ['gamma' => 'label,name', 'unknown' => 'id']));
    }

    public function test_a_collection_validates_the_fields_of_the_resources_it_collects(): void
    {
        self::assertSame([], self::errors(new Fields(UserCollection::class), ['user' => 'name', 'comment' => 'content']));
    }

    /**
     * @return list<string>
     */
    private static function errors(ValidationRule $rule, mixed $value): array
    {
        $errors = [];

        $rule->validate(
            'fields',
            $value,
            function (string $message) use (&$errors): PotentiallyTranslatedString {
                $errors[] = $message;

                return new PotentiallyTranslatedString($message, app('translator'));
            },
        );

        return $errors;
    }
}
