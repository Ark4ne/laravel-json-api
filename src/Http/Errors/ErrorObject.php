<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Http\Errors;

use Symfony\Component\HttpFoundation\Response;

/**
 * An error object (https://jsonapi.org/format/#error-objects): every member is optional, an empty one is left out.
 */
final readonly class ErrorObject
{
    /**
     * @param int|null $status The HTTP status of the error, written as a string
     * @param array{pointer?: string, parameter?: string, header?: string} $source
     * @param array<string, mixed> $links
     * @param array<string, mixed> $meta
     */
    public function __construct(
        public ?int $status = null,
        public ?string $title = null,
        public ?string $detail = null,
        public ?string $code = null,
        public array $source = [],
        public ?string $id = null,
        public array $links = [],
        public array $meta = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $members = [
            'id' => $this->id,
            'links' => $this->links,
            'status' => $this->status === null ? null : (string) $this->status,
            'code' => $this->code,
            'title' => $this->title,
            'detail' => $this->detail,
            'source' => $this->source,
            'meta' => $this->meta,
        ];

        return array_filter($members, static fn(mixed $member): bool => $member !== null && $member !== '' && $member !== []);
    }

    /**
     * The title of an HTTP status (`404` → `Not Found`); `null` for a status without one.
     */
    public static function titleOf(int $status): ?string
    {
        return Response::$statusTexts[$status] ?? null;
    }
}
