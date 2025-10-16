<?php

namespace Test\Unit\Requests\Rules;

use Ark4ne\JsonApi\Requests\Rules\Includes;
use PHPUnit\Framework\Attributes\DataProvider;
use Test\app\Http\Resources\UserResource;
use Test\TestCase;

class IncludesTest extends TestCase
{
    /**
     * @dataProvider validationDataProvider
     */
    #[DataProvider('validationDataProvider')]
    public function testPasses(mixed $values, array $expected)
    {
        $rule = new Includes(UserResource::class);
        $errors = [];

        $rule->validate('fields', $values, function($message) use (&$errors) {
            $errors[] = $message;
        });
        $this->assertEquals($expected, $errors);
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
                'posts.user.posts'
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
                '"posts" doesn\'t have relationship "unknown".'
            ]],
        ];
    }
}
