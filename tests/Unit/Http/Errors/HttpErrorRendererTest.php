<?php

declare(strict_types=1);

namespace Test\Unit\Http\Errors;

use Ark4ne\JsonApi\Http\Errors\HttpErrorRenderer;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Test\Support\JsonApiSchema;

final class HttpErrorRendererTest extends TestCase
{
    public function test_it_supports_http_exceptions_only(): void
    {
        $renderer = new HttpErrorRenderer();

        self::assertTrue($renderer->supports(new NotFoundHttpException()));
        self::assertFalse($renderer->supports(new RuntimeException()));
    }

    public function test_an_http_exception_keeps_its_status_and_its_headers(): void
    {
        $document = (new HttpErrorRenderer())->render(new TooManyRequestsHttpException(60, 'Slow down.'), Request::create('/'));

        self::assertSame(429, $document->status);
        self::assertSame(['Retry-After' => '60'], $document->headers);
        self::assertSame(
            ['errors' => [['status' => '429', 'title' => 'Too Many Requests', 'detail' => 'Slow down.']]],
            $document->toArray(),
        );
        self::assertSame([], JsonApiSchema::errors($document->toArray()));
    }

    public function test_an_http_exception_without_message_nor_known_status_has_neither_detail_nor_title(): void
    {
        $document = (new HttpErrorRenderer())->render(new HttpException(419), Request::create('/'));

        self::assertSame(['errors' => [['status' => '419']]], $document->toArray());
        self::assertSame([], JsonApiSchema::errors($document->toArray()));
    }
}
