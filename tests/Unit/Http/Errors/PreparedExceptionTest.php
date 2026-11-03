<?php

declare(strict_types=1);

namespace Test\Unit\Http\Errors;

use Ark4ne\JsonApi\Http\Errors\PreparedException;
use Illuminate\Auth\Access\AuthorizationException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

final class PreparedExceptionTest extends TestCase
{
    public function test_an_exception_is_read_as_is_or_from_the_http_exception_prepared_of_it(): void
    {
        $authorization = new AuthorizationException();

        self::assertSame($authorization, PreparedException::unwrap($authorization, AuthorizationException::class));
        self::assertSame(
            $authorization,
            PreparedException::unwrap(new AccessDeniedHttpException('', $authorization), AuthorizationException::class),
        );
        self::assertNull(PreparedException::unwrap(new AccessDeniedHttpException(), AuthorizationException::class));
        self::assertNull(PreparedException::unwrap(new RuntimeException('', 0, $authorization), AuthorizationException::class));
    }

    public function test_the_headers_of_an_http_exception_are_strings(): void
    {
        self::assertSame(['Retry-After' => '60'], PreparedException::headers(new TooManyRequestsHttpException(60)));
        self::assertSame(
            ['Allow' => ['GET', 'HEAD']],
            PreparedException::headers(new HttpException(405, headers: ['Allow' => ['GET', 'HEAD']])),
        );
        self::assertSame([], PreparedException::headers(new RuntimeException()));
    }
}
