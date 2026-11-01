<?php

declare(strict_types=1);

namespace Test\Unit\Http\Errors;

use Ark4ne\JsonApi\Configuration\JsonApiObject;
use Ark4ne\JsonApi\Http\Errors\AuthenticationErrorRenderer;
use Ark4ne\JsonApi\Http\Errors\AuthorizationErrorRenderer;
use Ark4ne\JsonApi\Http\Errors\ErrorRenderers;
use Ark4ne\JsonApi\Http\Errors\HttpErrorRenderer;
use Ark4ne\JsonApi\Http\Errors\ModelNotFoundErrorRenderer;
use Ark4ne\JsonApi\Http\Errors\ThrowableErrorRenderer;
use Ark4ne\JsonApi\Http\Errors\ValidationErrorRenderer;
use Illuminate\Http\Request;
use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Test\Unit\Http\Errors\Fixtures\DomainErrorRenderer;

final class ErrorRenderersTest extends TestCase
{
    public function test_the_renderers_are_ordered_by_decreasing_priority_then_as_given(): void
    {
        $renderers = new ErrorRenderers(
            null,
            $throwable = new ThrowableErrorRenderer(),
            $authentication = new AuthenticationErrorRenderer(),
            $validation = new ValidationErrorRenderer(),
            $authorization = new AuthorizationErrorRenderer(),
        );

        self::assertSame([$validation, $authentication, $authorization, $throwable], $renderers->all());
    }

    public function test_the_default_renderers_are_those_of_the_package(): void
    {
        self::assertSame([
            ValidationErrorRenderer::class,
            AuthenticationErrorRenderer::class,
            AuthorizationErrorRenderer::class,
            ModelNotFoundErrorRenderer::class,
            HttpErrorRenderer::class,
            ThrowableErrorRenderer::class,
        ], array_map(get_class(...), ErrorRenderers::default(null)->all()));
    }

    public function test_the_first_renderer_supporting_the_exception_renders_it_with_the_jsonapi_member(): void
    {
        $renderers = new ErrorRenderers(new JsonApiObject(), new ThrowableErrorRenderer(), new DomainErrorRenderer());

        $document = $renderers->render(new LogicException('Domain'), Request::create('/'));

        self::assertSame(409, $document?->status);
        self::assertEquals(new JsonApiObject(), $document->jsonapi);
        self::assertSame(500, $renderers->render(new RuntimeException(), Request::create('/'))?->status);
    }

    public function test_no_document_when_no_renderer_supports_the_exception(): void
    {
        self::assertNull((new ErrorRenderers(null, new DomainErrorRenderer()))->render(new RuntimeException(), Request::create('/')));
    }

    public function test_the_debug_flag_goes_to_the_renderer_of_unexpected_exceptions(): void
    {
        $document = ErrorRenderers::default(null, debug: true)->render(new RuntimeException('Boom'), Request::create('/'));

        self::assertSame('Boom', $document?->errors[0]->detail);
    }
}
