<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Resources\Concerns;

use Illuminate\Http\Request;

/**
 * @template T The model of the resource
 */
trait Links
{
    use PrepareData;

    /**
     * @see https://jsonapi.org/format/#document-resource-object-links
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return iterable<string, string|array<string, mixed>|null>|iterable<array-key, \Ark4ne\JsonApi\Descriptors\Values\Value<T>>|null
     *         Links by name: a URL, a link object or `null`; or descriptors
     *
     * ```
     * return [
     *     'self' => route('api.user.show', ['id' => $this->id]),
     * ];
     * ```
     */
    protected function toLinks(Request $request): ?iterable
    {
        return null;
    }
}
