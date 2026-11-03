<?php

declare(strict_types=1);

namespace Test\Unit\Http\Errors;

use Ark4ne\JsonApi\Http\Errors\ThrowableErrorRenderer;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Test\Support\JsonApiSchema;

final class ThrowableErrorRendererTest extends TestCase
{
    public function test_it_supports_any_exception_but_an_http_response_exception(): void
    {
        $renderer = new ThrowableErrorRenderer();

        self::assertTrue($renderer->supports(new RuntimeException()));
        self::assertFalse($renderer->supports(new HttpResponseException(new Response())));
    }

    public function test_an_unexpected_exception_is_a_500_without_its_message(): void
    {
        $document = (new ThrowableErrorRenderer())->render(new RuntimeException('Secret'), Request::create('/'));

        self::assertSame(500, $document->status);
        self::assertSame(['errors' => [['status' => '500', 'title' => 'Internal Server Error']]], $document->toArray());
        self::assertSame([], JsonApiSchema::errors($document->toArray()));
    }

    public function test_in_debug_the_message_and_the_location_are_rendered(): void
    {
        $exception = new RuntimeException('Secret');

        $error = (new ThrowableErrorRenderer(debug: true))->render($exception, Request::create('/'))->errors[0];

        self::assertSame('Secret', $error->detail);
        self::assertSame([
            'exception' => RuntimeException::class,
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ], $error->meta);
    }
}
