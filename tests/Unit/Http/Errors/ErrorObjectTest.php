<?php

declare(strict_types=1);

namespace Test\Unit\Http\Errors;

use Ark4ne\JsonApi\Http\Errors\ErrorObject;
use PHPUnit\Framework\TestCase;

final class ErrorObjectTest extends TestCase
{
    public function test_every_member_given_is_written_the_status_as_a_string(): void
    {
        $error = new ErrorObject(
            status: 422,
            title: 'Unprocessable Content',
            detail: 'The title field is required.',
            code: 'required',
            source: ['pointer' => '/data/attributes/title'],
            id: 'e1',
            links: ['about' => 'https://example.com/errors/required'],
            meta: ['rule' => 'required'],
        );

        self::assertSame([
            'id' => 'e1',
            'links' => ['about' => 'https://example.com/errors/required'],
            'status' => '422',
            'code' => 'required',
            'title' => 'Unprocessable Content',
            'detail' => 'The title field is required.',
            'source' => ['pointer' => '/data/attributes/title'],
            'meta' => ['rule' => 'required'],
        ], $error->toArray());
    }

    public function test_an_empty_member_is_left_out(): void
    {
        self::assertSame(['status' => '500'], (new ErrorObject(status: 500, detail: ''))->toArray());
        self::assertSame([], (new ErrorObject())->toArray());
    }
}
