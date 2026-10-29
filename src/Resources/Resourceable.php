<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Resources;

interface Resourceable
{
    /**
     * @param \Illuminate\Http\Request $request
     * @param bool                     $included
     *
     * @return array<mixed>
     */
    public function toArray(mixed $request, bool $included = true): array;
}
