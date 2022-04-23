<?php

namespace Ark4ne\JsonApi\Resources;

interface Resourceable
{
    /**
     * @param      $request
     * @param bool $included
     *
     * @return array{type: string, id: string}|array<int, array{type: string, id: string}>
     */
    public function toArray($request, bool $included = true): array;
}
