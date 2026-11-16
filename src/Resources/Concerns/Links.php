<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Resources\Concerns;

use Closure;
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
     * @return iterable<array-key, mixed>|null Links by name: a URL, a link object, `null`, or a closure returning one;
     *         never a descriptor (a link holds no history)
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
