<?php

declare(strict_types=1);

namespace Test\Unit\Http\Errors;

use Ark4ne\JsonApi\Http\Errors\AuthorizationErrorRenderer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Access\Response;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Test\Support\JsonApiSchema;

final class AuthorizationErrorRendererTest extends TestCase
{
    public function test_it_supports_an_authorization_exception_as_is_or_as_laravel_prepared_it(): void
    {
        $renderer = new AuthorizationErrorRenderer();

        self::assertTrue($renderer->supports(new AuthorizationException()));
        self::assertTrue($renderer->supports(new AccessDeniedHttpException('Denied.', new AuthorizationException())));
        self::assertFalse($renderer->supports(new AccessDeniedHttpException('Denied.')));
    }

    public function test_an_authorization_exception_is_a_403_with_its_message(): void
    {
        $document = (new AuthorizationErrorRenderer())->render(new AuthorizationException('Not yours.'), Request::create('/'));

        self::assertSame(403, $document->status);
        self::assertSame(
            ['errors' => [['status' => '403', 'title' => 'Forbidden', 'detail' => 'Not yours.']]],
            $document->toArray(),
        );
        self::assertSame([], JsonApiSchema::errors($document->toArray()));
    }

    public function test_the_status_given_to_the_authorization_is_kept(): void
    {
        $renderer = new AuthorizationErrorRenderer();
        $notFound = (new AuthorizationException('Hidden.'))->asNotFound();

        self::assertSame(404, $renderer->render($notFound, Request::create('/'))->status);
        self::assertSame(404, $renderer->render(new HttpException(404, 'Hidden.', $notFound), Request::create('/'))->status);
        self::assertSame('Not Found', $renderer->render($notFound, Request::create('/'))->errors[0]->title);
    }

    public function test_a_denial_with_a_status_has_only_the_message_of_its_response(): void
    {
        $renderer = new AuthorizationErrorRenderer();
        // `Response::denyAsNotFound()->authorize()`: the message of the exception is the default one.
        $hidden = (new AuthorizationException())->asNotFound();
        $prepared = new HttpException(404, 'Not Found', $hidden, ['X-Reason' => 'policy']);

        self::assertSame(['errors' => [['status' => '404', 'title' => 'Not Found']]], $renderer->render($hidden, Request::create('/'))->toArray());
        self::assertSame(['errors' => [['status' => '404', 'title' => 'Not Found']]], $renderer->render($prepared, Request::create('/'))->toArray());
        self::assertSame(['X-Reason' => 'policy'], $renderer->render($prepared, Request::create('/'))->headers);

        try {
            Response::denyWithStatus(410, 'Archived.')->authorize();
        } catch (AuthorizationException $explained) {
            // As Laravel's handler prepares it: the message of the response.
            $document = $renderer->render(new HttpException(410, 'Archived.', $explained), Request::create('/'));
            self::assertSame('Archived.', $document->errors[0]->detail);
            self::assertNull($renderer->render($explained, Request::create('/'))->errors[0]->detail);
        }
        self::assertSame([], JsonApiSchema::errors($renderer->render($prepared, Request::create('/'))->toArray()));
    }
}
