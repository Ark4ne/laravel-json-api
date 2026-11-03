<?php

declare(strict_types=1);

namespace Test\Unit\Http\Errors;

use Ark4ne\JsonApi\Http\Errors\ModelNotFoundErrorRenderer;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Test\app\Models\Post;
use Test\Support\JsonApiSchema;

final class ModelNotFoundErrorRendererTest extends TestCase
{
    public function test_it_supports_a_model_not_found_as_is_or_as_laravel_prepared_it(): void
    {
        $renderer = new ModelNotFoundErrorRenderer();
        $exception = (new ModelNotFoundException())->setModel(Post::class, [1]);

        self::assertTrue($renderer->supports($exception));
        self::assertTrue($renderer->supports(new NotFoundHttpException($exception->getMessage(), $exception)));
        self::assertFalse($renderer->supports(new NotFoundHttpException()));
    }

    public function test_a_model_not_found_is_a_404_without_the_class_of_the_model(): void
    {
        $exception = (new ModelNotFoundException())->setModel(Post::class, [1]);

        $document = (new ModelNotFoundErrorRenderer())->render(
            new NotFoundHttpException($exception->getMessage(), $exception),
            Request::create('/'),
        );

        self::assertSame(404, $document->status);
        self::assertSame(['errors' => [['status' => '404', 'title' => 'Not Found']]], $document->toArray());
        self::assertSame([], JsonApiSchema::errors($document->toArray()));
    }

    public function test_the_headers_of_the_prepared_http_exception_are_kept(): void
    {
        $exception = new NotFoundHttpException('', new ModelNotFoundException(), headers: ['X-Reason' => 'gone']);

        self::assertSame(['X-Reason' => 'gone'], (new ModelNotFoundErrorRenderer())->render($exception, Request::create('/'))->headers);
    }
}
