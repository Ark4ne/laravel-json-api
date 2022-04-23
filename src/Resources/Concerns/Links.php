<?php

namespace Ark4ne\JsonApi\Resources\Concerns;

use Illuminate\Http\Request;

trait Links
{
    /**
     * @see https://jsonapi.org/format/#document-resource-object-links
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array<string, string>|null
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
