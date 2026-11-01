<?php

declare(strict_types=1);

namespace Test\Unit\Http\Errors;

use Ark4ne\JsonApi\Http\Errors\AuthenticationErrorRenderer;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Test\Support\JsonApiSchema;

final class AuthenticationErrorRendererTest extends TestCase
{
    public function test_it_supports_authentication_exceptions_only(): void
    {
        $renderer = new AuthenticationErrorRenderer();

        self::assertTrue($renderer->supports(new AuthenticationException()));
        self::assertFalse($renderer->supports(new RuntimeException()));
    }

    public function test_an_authentication_exception_is_a_401_with_its_message(): void
    {
        $document = (new AuthenticationErrorRenderer())->render(new AuthenticationException(), Request::create('/'));

        self::assertSame(401, $document->status);
        self::assertSame(
            ['errors' => [['status' => '401', 'title' => 'Unauthorized', 'detail' => 'Unauthenticated.']]],
            $document->toArray(),
        );
        self::assertSame([], JsonApiSchema::errors($document->toArray()));
    }
}
