<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Resources\Concerns;

use Illuminate\Http\Request;

trait Links
{
    use PrepareData;

    /**
     * @see https://jsonapi.org/format/#document-resource-object-links
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return iterable<string, string>|iterable<array-key, \Ark4ne\JsonApi\Descriptors\Values\Value>|null
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
