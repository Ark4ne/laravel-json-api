<?php

namespace Test\Unit\Requests\Rules;

use Ark4ne\JsonApi\Requests\Rules\Fields;
use PHPUnit\Framework\Attributes\DataProvider;
use Test\app\Http\Resources\UserResource;
use Test\TestCase;

class FieldsTest extends TestCase
{
    /**
     * @dataProvider validationDataProvider
     */
    #[DataProvider('validationDataProvider')]
    public function testPasses(mixed $values, array $expected)
    {
        $rule = new Fields(UserResource::class);
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
                '"user" doesn\'t have fields "unknown".'
            ]],
            [['user' => 'name,email', 'post' => 'unknown'], [
                'The selected :attribute is invalid.',
                '"post" doesn\'t have fields "unknown".'
            ]],
        ];
    }
}
