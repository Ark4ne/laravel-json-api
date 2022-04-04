<?php

namespace Ark4ne\JsonApi\Resource;

interface Resourceable
{
    public function toArray($request, bool $minimal = false): array;
}
