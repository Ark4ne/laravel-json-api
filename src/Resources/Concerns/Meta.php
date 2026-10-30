<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Resources\Concerns;

use Illuminate\Http\Request;

/**
 * @phpstan-require-extends \Ark4ne\JsonApi\Resources\JsonApiResource
 */
trait Meta
{
    use PrepareData;

    /**
     * @see https://jsonapi.org/format/#document-resource-objects
     * @see https://jsonapi.org/format/#document-meta
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return iterable<string, mixed>|iterable<array-key, \Ark4ne\JsonApi\Descriptors\Values\Value>|null
     *
     * ```
     * return [
     *     'created_at' => $this->created_at->format(DateTimeInterface::ATOM),
     *     'updated_at' => $this->updated_at->format(DateTimeInterface::ATOM),
     * ];
     * ```
     */
    protected function toResourceMeta(Request $request): ?iterable
    {
        return null;
    }

    /**
     * Top-level meta of the document, added by the primary resources only. `toMeta()` of 1.x, renamed: a resource
     * still declaring `toMeta()` cannot be compiled.
     *
     * @see https://jsonapi.org/format/#document-meta
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return iterable<string, mixed>|iterable<array-key, \Ark4ne\JsonApi\Descriptors\Values\Value>|null
     */
    protected function toDocumentMeta(Request $request): ?iterable
    {
        return null;
    }
}
