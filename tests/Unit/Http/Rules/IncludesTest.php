<?php

declare(strict_types=1);

namespace Test\Unit\Http\Rules;

use Ark4ne\JsonApi\Compilation\Definitions;
use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Http\Rules\Includes;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Test\app\Http\Resources\NativePostResource;
use Test\app\Http\Resources\Revisions\PostResource as RevisedPostResource;
use Test\app\Http\Resources\UserCollection;
use Test\app\Http\Resources\UserResource;
use Test\app\Revisions\ApiRevision;
use Test\TestCase;
use Test\Unit\Resources\Fixtures\AlphaResource;

class IncludesTest extends TestCase
{
    /**
     * @param list<string> $expected
     */
    #[DataProvider('validationDataProvider')]
    public function test_the_includes_are_validated_against_the_resource(mixed $values, array $expected): void
    {
        self::assertEquals($expected, self::ruleErrors('include', new Includes(UserResource::class), $values));
    }

    /**
     * @return list<array{mixed, list<string>}>
     */
    public static function validationDataProvider(): array
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
        self::assertSame([], self::ruleErrors('include', new Includes(AlphaResource::class), 'beta.gamma.alpha.gammas,gammas.lazy'));
        self::assertSame([
            'The selected :attribute is invalid.',
            '"beta.gamma" doesn\'t have relationship "unknown".',
        ], self::ruleErrors('include', new Includes(AlphaResource::class), 'beta.gamma.unknown'));
    }

    public function test_nothing_is_included_under_a_native_resource(): void
    {
        self::assertSame([], self::ruleErrors('include', new Includes(NativePostResource::class), 'user,comments'));
        self::assertSame([
            'The selected :attribute is invalid.',
            '"user" doesn\'t have relationship "posts".',
        ], self::ruleErrors('include', new Includes(NativePostResource::class), 'user.posts'));
    }

    public function test_a_collection_validates_the_includes_of_the_resources_it_collects(): void
    {
        self::assertSame([], self::ruleErrors('include', new Includes(UserCollection::class), 'posts.user,comments'));
    }

    public function test_the_rule_tells_the_resource_it_validates_against(): void
    {
        self::assertSame(UserResource::class, (new Includes(UserResource::class))->resource());
        self::assertSame(UserCollection::class, (new Includes(UserCollection::class))->resource());
    }


    public function test_an_include_deeper_than_the_maximum_depth_fails(): void
    {
        self::assertSame([], self::ruleErrors('include', new Includes(UserResource::class, maxDepth: 2), 'posts.user,comments'));
        self::assertSame([
            'The selected :attribute is invalid.',
            '"posts.user.posts" is deeper than the maximum include depth of 2.',
        ], self::ruleErrors('include', new Includes(UserResource::class, maxDepth: 2), 'posts.user.posts'));
    }

    public function test_the_include_is_parsed_as_the_rendering_parses_it(): void
    {
        self::assertSame([], self::ruleErrors('include', new Includes(UserResource::class), 'posts , comments,,posts.user'));
    }

    public function test_the_maximum_depth_defaults_to_the_configuration(): void
    {
        Definitions::configure(Configuration::fromArray(['include' => ['max-depth' => 1]]));

        try {
            self::assertSame([
                'The selected :attribute is invalid.',
                '"posts.user" is deeper than the maximum include depth of 1.',
            ], self::ruleErrors('include', new Includes(UserResource::class), 'posts.user'));
        } finally {
            Definitions::configure(Configuration::defaults());
        }
    }

    public function test_the_messages_are_the_translations_of_the_includes(): void
    {
        // Loaded first: the lines added complete the file instead of replacing it.
        app('translator')->load('jsonapi', 'validation', 'en');
        app('translator')->addLines(['validation.includes.invalid_includes' => ':include has no :relation'], 'en', 'jsonapi');

        self::assertSame(
            ['The selected :attribute is invalid.', 'user has no unknown'],
            self::ruleErrors('include', new Includes(UserResource::class), 'unknown'),
        );
    }

    public function test_a_rule_validates_each_value_on_its_own(): void
    {
        $rule = new Includes(UserResource::class);

        self::assertNotSame([], self::ruleErrors('include', $rule, 'unknown'));
        self::assertSame([], self::ruleErrors('include', $rule, 'posts'));
    }

    public function test_the_validator_names_the_attribute(): void
    {
        $validator = Validator::make(['include' => 'unknown'], ['include' => [new Includes(UserResource::class)]]);

        self::assertSame([
            'The selected include is invalid.',
            '"user" doesn\'t have relationship "unknown".',
        ], $validator->errors()->get('include'));
    }

    public function test_an_old_client_may_include_an_old_relationship(): void
    {
        $rule = new Includes(RevisedPostResource::class, revision: ApiRevision::Labels);

        self::assertSame([], self::ruleErrors('include', $rule, 'writer,comments.commenter'));
        self::assertSame([
            'The selected :attribute is invalid.',
            '"posts" doesn\'t have relationship "writer".',
        ], self::ruleErrors('include', new Includes(RevisedPostResource::class), 'writer'));
    }

    public function test_an_old_client_may_not_include_a_new_relationship(): void
    {
        self::assertSame([], self::ruleErrors('include', new Includes(RevisedPostResource::class), 'user.posts,comments.user'));
        self::assertSame([
            'The selected :attribute is invalid.',
            '"posts" doesn\'t have relationship "user".',
            '"comments" doesn\'t have relationship "user".',
        ], self::ruleErrors('include', new Includes(RevisedPostResource::class, revision: ApiRevision::Labels), 'user,comments.user'));
    }

    public function test_the_rule_tells_the_revision_it_validates_against(): void
    {
        self::assertNull((new Includes(UserResource::class))->revision());
        self::assertSame(ApiRevision::Labels, (new Includes(UserResource::class, revision: ApiRevision::Labels))->revision());
    }
}
