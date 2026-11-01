<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Http\Errors;

use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Reads an exception as is or as the `HttpException` Laravel's handler prepared of it (`ModelNotFoundException` →
 * `NotFoundHttpException`, `AuthorizationException` → `AccessDeniedHttpException`…), before its render callbacks.
 *
 * @internal
 */
final class PreparedException
{
    /**
     * The exception when it is a `$class`, or the one an `HttpException` was prepared of; `null` otherwise.
     *
     * @template T of Throwable
     *
     * @param class-string<T> $class
     *
     * @return T|null
     */
    public static function unwrap(Throwable $exception, string $class): ?Throwable
    {
        $previous = $exception instanceof HttpExceptionInterface ? $exception->getPrevious() : null;

        return match (true) {
            $exception instanceof $class => $exception,
            $previous instanceof $class => $previous,
            default => null,
        };
    }

    /**
     * The headers of an `HttpException`, as strings (`Retry-After` is given as an integer); none for another exception.
     *
     * @return array<string, string|list<string>>
     */
    public static function headers(Throwable $exception): array
    {
        $headers = [];

        foreach ($exception instanceof HttpExceptionInterface ? $exception->getHeaders() : [] as $name => $value) {
            $headers[(string) $name] = is_array($value) ? array_values(array_map(self::header(...), $value)) : self::header($value);
        }

        return $headers;
    }

    private static function header(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
