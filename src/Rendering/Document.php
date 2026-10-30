<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Rendering;

use Ark4ne\JsonApi\Exceptions\RenderingException;
use JsonSerializable;

/**
 * A top-level JSON:API document (https://jsonapi.org/format/#document-top-level). The only place that knows its shape.
 */
final readonly class Document implements JsonSerializable
{
    /**
     * @param ResourceObject|list<ResourceObject>|null $data
     * @param list<ResourceObject> $included
     * @param array<array-key, mixed> $meta
     * @param array<array-key, mixed> $links
     *
     * @throws RenderingException when `meta` or `links` is a list
     */
    public function __construct(
        public ResourceObject|array|null $data,
        public array $included = [],
        public array $meta = [],
        public array $links = [],
    ) {
        foreach (['meta' => $meta, 'links' => $links] as $name => $member) {
            if ($member !== [] && array_is_list($member)) {
                throw RenderingException::memberNotAnObject($name, 'the document');
            }
        }
    }

    /**
     * @param array<array-key, mixed> $meta
     */
    public function withMeta(array $meta): self
    {
        return new self($this->data, $this->included, array_replace($this->meta, $meta), $this->links);
    }

    /**
     * @param array<array-key, mixed> $links
     */
    public function withLinks(array $links): self
    {
        return new self($this->data, $this->included, $this->meta, array_replace($this->links, $links));
    }

    /**
     * `data` always; `included`, `links` and `meta` only when not empty.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $document = [
            'data' => match (true) {
                $this->data instanceof ResourceObject => $this->data->toArray(),
                is_array($this->data) => array_map(static fn(ResourceObject $object) => $object->toArray(), $this->data),
                default => null,
            },
        ];

        if ($this->included !== []) {
            $document['included'] = array_map(static fn(ResourceObject $object) => $object->toArray(), $this->included);
        }

        if ($this->links !== []) {
            $document['links'] = $this->links;
        }

        if ($this->meta !== []) {
            $document['meta'] = $this->meta;
        }

        return $document;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
