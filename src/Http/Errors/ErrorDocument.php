<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Http\Errors;

use Ark4ne\JsonApi\Configuration\JsonApiObject;
use Illuminate\Http\JsonResponse;

/**
 * A document of errors (https://jsonapi.org/format/#errors) and the HTTP status and headers of its response.
 */
final readonly class ErrorDocument
{
    /**
     * @param int $status The status of the response
     * @param non-empty-list<ErrorObject> $errors
     * @param array<string, string|list<string>> $headers The headers of the response (`Retry-After`, `Allow`…)
     * @param array<string, mixed> $meta
     */
    public function __construct(
        public int $status,
        public array $errors,
        public array $headers = [],
        public array $meta = [],
        public ?JsonApiObject $jsonapi = null,
    ) {}

    /**
     * The document with the `jsonapi` member, `null` for none.
     */
    public function withJsonApi(?JsonApiObject $jsonapi): self
    {
        return new self($this->status, $this->errors, $this->headers, $this->meta, $jsonapi);
    }

    /**
     * `jsonapi` when given, `errors` always, `meta` when not empty.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $document = $this->jsonapi === null ? [] : ['jsonapi' => $this->jsonapi->toArray()];
        $document['errors'] = array_map(static fn(ErrorObject $error): array => $error->toArray(), $this->errors);

        if ($this->meta !== []) {
            $document['meta'] = $this->meta;
        }

        return $document;
    }

    /**
     * The response, served as `application/vnd.api+json`.
     */
    public function toResponse(): JsonResponse
    {
        return new JsonResponse(
            $this->toArray(),
            $this->status,
            [...$this->headers, 'Content-Type' => 'application/vnd.api+json'],
            // An error renderer never fails on a message that is not UTF-8 (a database driver error in debug).
            JSON_INVALID_UTF8_SUBSTITUTE,
        );
    }
}
