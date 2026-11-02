<?php

declare(strict_types=1);

namespace Test\Unit\Http\Rules;

use Ark4ne\JsonApi\Http\Rules\Fields;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Translation\PotentiallyTranslatedString;
use PHPUnit\Framework\Attributes\DataProvider;
use Test\app\Http\Resources\NativePostResource;
use Test\app\Http\Resources\UserCollection;
use Test\app\Http\Resources\UserResource;
use Test\TestCase;
use Test\Unit\Http\Rules\Fixtures\UntypedNativeHolderResource;
use Test\Unit\Resources\Fixtures\AlphaResource;

class FieldsTest extends TestCase
{
    /**
     * @param list<string> $expected
     */
    #[DataProvider('validationDataProvider')]
    public function testPasses(mixed $values, array $expected): void
    {
        self::assertEquals($expected, self::errors(new Fields(UserResource::class), $values));
    }

    /**
     * @return list<array{mixed, list<string>}>
     */
    public static function validationDataProvider(): array
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
                '"unknown" doesn\'t exist.',
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

    public function test_the_fields_are_parsed_as_the_rendering_parses_them(): void
    {
        self::assertSame([], self::errors(new Fields(UserResource::class), ['user' => ' name , posts,', 'post' => null]));
        self::assertSame(
            ['The selected :attribute is invalid.'],
            self::errors(new Fields(UserResource::class), ['user' => ['name']]),
        );
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
            '"unknown" doesn\'t exist.',
        ], self::errors(new Fields(AlphaResource::class), ['gamma' => 'label,name', 'unknown' => 'id']));
    }

    public function test_the_fields_of_a_native_type_pass_as_they_are(): void
    {
        self::assertSame([], self::errors(new Fields(NativePostResource::class), [
            'post' => 'title,user',
            'users' => 'name,unknown',
        ]));
        self::assertSame([
            'The selected :attribute is invalid.',
            '"pots" doesn\'t exist.',
        ], self::errors(new Fields(NativePostResource::class), ['pots' => 'title']));
    }

    public function test_a_type_no_definition_has_passes_once_a_native_resource_of_unknown_type_is_reachable(): void
    {
        self::assertSame([], self::errors(new Fields(UntypedNativeHolderResource::class), ['anything' => 'name']));
        self::assertSame([
            'The selected :attribute is invalid.',
            '"holder" doesn\'t have fields "unknown".',
        ], self::errors(new Fields(UntypedNativeHolderResource::class), ['holder' => 'unknown']));
    }

    public function test_a_collection_validates_the_fields_of_the_resources_it_collects(): void
    {
        self::assertSame([], self::errors(new Fields(UserCollection::class), ['user' => 'name', 'comment' => 'content']));
    }

    public function test_the_rule_tells_the_resource_it_validates_against(): void
    {
        self::assertSame(UserResource::class, (new Fields(UserResource::class))->resource());
        self::assertSame(UserCollection::class, (new Fields(UserCollection::class))->resource());
    }

    /**
     * @return list<string>
     */
    private static function errors(ValidationRule $rule, mixed $value): array
    {
        /** @var list<PotentiallyTranslatedString> $messages */
        $messages = [];

        $rule->validate(
            'fields',
            $value,
            function (string $message) use (&$messages): PotentiallyTranslatedString {
                return $messages[] = new PotentiallyTranslatedString($message, app('translator'));
            },
        );

        // As the validator reads them: translated when the rule asked for it.
        return array_map(static fn(PotentiallyTranslatedString $message): string => (string) $message, $messages);
    }

    public function test_the_messages_are_the_translations_of_the_fields(): void
    {
        // Loaded first: the lines added complete the file instead of replacing it.
        app('translator')->load('jsonapi', 'validation', 'en');
        app('translator')->addLines(['validation.fields.invalid_resource' => 'no :resource'], 'en', 'jsonapi');

        self::assertSame(
            ['The selected :attribute is invalid.', 'no unknown'],
            self::errors(new Fields(UserResource::class), ['unknown' => 'a']),
        );
    }

    public function test_a_rule_validates_each_value_on_its_own(): void
    {
        $rule = new Fields(UserResource::class);

        self::assertNotSame([], self::errors($rule, ['unknown' => 'a']));
        self::assertSame([], self::errors($rule, ['user' => 'name']));
    }

    public function test_the_validator_names_the_attribute(): void
    {
        $validator = Validator::make(['fields' => ['unknown' => 'a']], ['fields' => [new Fields(UserResource::class)]]);

        self::assertSame([
            'The selected fields is invalid.',
            '"unknown" doesn\'t exist.',
        ], $validator->errors()->get('fields'));
    }
}
