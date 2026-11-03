<?php

declare(strict_types=1);

namespace Test\Unit\Resources;

use Ark4ne\JsonApi\Resources\NativeResource;
use Illuminate\Http\Request;
use Test\TestCase;

final class NativeResourceTest extends TestCase
{
    public function test_the_request_of_the_native_resources_has_no_include_and_keeps_the_rest(): void
    {
        $request = Request::create('/?include=user&fields[users]=name', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], (string) json_encode(['include' => 'posts', 'data' => ['type' => 'post']]));

        $native = NativeResource::requestOf($request);

        self::assertSame([], $native->sparseIncluded());
        self::assertSame(['name'], $native->sparseFields('users'));
        self::assertSame(['type' => 'post'], $native->json('data'));
    }

    public function test_the_request_rendered_is_left_unchanged(): void
    {
        $request = Request::create('/?include=user', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], (string) json_encode(['include' => 'posts']));

        NativeResource::requestOf($request);

        self::assertSame('user', $request->query('include'));
        self::assertSame('posts', $request->json('include'));
    }
}
