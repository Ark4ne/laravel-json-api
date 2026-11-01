<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Configuration;

/**
 * The top-level `jsonapi` member of the documents (https://jsonapi.org/format/#document-jsonapi-object), as
 * `document.jsonapi` configures it.
 */
final readonly class JsonApiObject
{
    /** The JSON:API media type, without parameter */
    public const MEDIA_TYPE = 'application/vnd.api+json';

    /**
     * @param string $version Highest version of the specification the server supports
     * @param list<string> $ext URIs of the extensions applied to the documents
     * @param list<string> $profile URIs of the profiles applied to the documents
     * @param array<string, mixed> $meta
     */
    public function __construct(
        public string $version = '1.1',
        public array $ext = [],
        public array $profile = [],
        public array $meta = [],
    ) {}

    /**
     * The `Content-Type` of the documents: the media type, with the `ext` and `profile` parameters naming the
     * extensions and profiles applied (https://jsonapi.org/format/#media-type-parameter-rules).
     */
    public function contentType(): string
    {
        $contentType = self::MEDIA_TYPE;

        foreach (['ext' => $this->ext, 'profile' => $this->profile] as $parameter => $uris) {
            if ($uris !== []) {
                $contentType .= "; {$parameter}=\"" . implode(' ', $uris) . '"';
            }
        }

        return $contentType;
    }

    /**
     * `version` always; `ext`, `profile` and `meta` only when not empty.
     *
     * @return array{version: string, ext?: list<string>, profile?: list<string>, meta?: array<string, mixed>}
     */
    public function toArray(): array
    {
        $jsonapi = ['version' => $this->version];

        if ($this->ext !== []) {
            $jsonapi['ext'] = $this->ext;
        }
        if ($this->profile !== []) {
            $jsonapi['profile'] = $this->profile;
        }
        if ($this->meta !== []) {
            $jsonapi['meta'] = $this->meta;
        }

        return $jsonapi;
    }
}
