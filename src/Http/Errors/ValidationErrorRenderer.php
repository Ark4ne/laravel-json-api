<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Http\Errors;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * A `ValidationException`: its status (`422` by default), one error per message, its `source` being the query
 * parameter (`fields.post` → `parameter: fields[post]`) when the request has it or has no body (`GET`), otherwise the
 * member of the body (`data.attributes.title` → `pointer: /data/attributes/title`). An exception with a response of its
 * own is left to Laravel, which sends it.
 */
final readonly class ValidationErrorRenderer implements ErrorRenderer
{
    public const PRIORITY = 400;

    public function supports(Throwable $exception): bool
    {
        return $exception instanceof ValidationException && $exception->response === null;
    }

    public function render(Throwable $exception, Request $request): ErrorDocument
    {
        $status = $exception instanceof ValidationException ? $exception->status : 422;
        $errors = [];

        /** @var array<array-key, list<string>> $messages An integer key for a list body (`'*' => 'array'`) */
        $messages = $exception instanceof ValidationException ? $exception->errors() : [];
        foreach ($messages as $key => $details) {
            $source = self::source((string) $key, $request);
            foreach ($details as $detail) {
                $errors[] = new ErrorObject(
                    status: $status,
                    title: ErrorObject::titleOf($status),
                    detail: $detail,
                    source: $source,
                );
            }
        }

        return new ErrorDocument(
            $status,
            $errors !== [] ? $errors : [new ErrorObject(status: $status, title: ErrorObject::titleOf($status))],
        );
    }

    /**
     * The query parameter when the request has it or has no body (`fields.post` → `fields[post]`), otherwise the JSON
     * pointer of the member of the body (`data.attributes.title` → `/data/attributes/title`).
     *
     * @return array{pointer: string}|array{parameter: string}
     */
    private static function source(string $key, Request $request): array
    {
        $segments = explode('.', $key);
        $root = array_shift($segments);

        if ($request->query->has($root) || $request->isMethodSafe()) {
            return ['parameter' => $root . implode('', array_map(static fn(string $segment) => "[{$segment}]", $segments))];
        }

        // RFC 6901: `~` then `/` escaped in each segment.
        return ['pointer' => '/' . implode('/', array_map(
            static fn(string $segment) => str_replace(['~', '/'], ['~0', '~1'], $segment),
            [$root, ...$segments],
        ))];
    }
}
