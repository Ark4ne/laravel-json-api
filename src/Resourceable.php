<?php

namespace Ark4ne\JsonApi\Resource;

interface Resourceable
{
    /**
     * @param      $request
     * @param bool $minimal
     *
     * @return array{type: string, id: string}|array<int, array{type: string, id: string}>
     */
    public function toArray($request, bool $minimal = false): array;
}
